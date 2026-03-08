<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use Hostinger\AiAssistant\Functions;
use Hostinger\AiAssistant\Mcp\Dto\RestOperationConfig;
use stdClass;
use Throwable;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

abstract class RestEndpointTool {
    protected string $type = 'tool';
    /**
     * @var RestOperationConfig[]
     */
    protected array $operation_configs = array();

    abstract public function register(): void;

    protected function register_operations( array $operations, string $controller_class, string $route, string $resource_type = 'post' ): void {
        foreach ( $operations as $operation => $config ) {
            $operation_config = RestOperationConfig::from_array( $operation, $config, $controller_class, $route, $resource_type );
            $this->register_single_operation( $operation_config );
        }
    }

    protected function register_single_operation( RestOperationConfig $config ): void {
        if ( ! $config->is_valid() ) {
            Functions::log_event( "Missing required configuration for operation: {$config->get_operation()}" );
            return;
        }

        try {
            $this->operation_configs[ $config->get_tool_name() ] = $config;

            $input_schema = $this->get_input_schema( $config );
            if ( $config->has_input_schema_modifications() ) {
                $input_schema = $this->apply_schema_modifications( $input_schema, $config->get_input_schema_modifications() );
            }

            $ability_args = array(
                'label'               => $config->get_label(),
                'description'         => $config->get_description(),
                'category'            => 'hostinger-ai-assistant',
                'input_schema'        => $input_schema,
                'execute_callback'    => function ( $input ) use ( $config ) {
                    return $this->execute_operation( $config, $input );
                },
                'permission_callback' => $config->get_permission_callback() ?? function () {
                    return current_user_can( 'manage_options' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => $this->type,
                    ),
                ),
            );

            if ( $config->has_meta_modifications() ) {
                $ability_args['meta'] = $this->apply_meta_modifications( $ability_args['meta'], $config->get_meta_modifications() );
            }

            wp_register_ability( $config->get_tool_name(), $ability_args );
        } catch ( Throwable $e ) {
            Functions::log_event( 'Failed to register ability: ' . $e->getMessage() );
        }
    }

    protected function get_input_schema( RestOperationConfig $config ): array {
        $controller_class = $config->get_controller_class();
        $controller       = new $controller_class( $config->get_resource_type() );
        $operation        = $config->get_operation();
        $skip_ids         = $config->should_skip_ids();

        switch ( $operation ) {
            case 'list':
                if ( method_exists( $controller, 'get_collection_params' ) ) {
                    return $this->sanitize_args_to_schema( $controller->get_collection_params() );
                }
                break;
            case 'create':
                if ( method_exists( $controller, 'get_endpoint_args_for_item_schema' ) ) {
                    $args = $controller->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE );
                    return $this->sanitize_args_to_schema( $args );
                }
                break;
            case 'update':
                if ( method_exists( $controller, 'get_endpoint_args_for_item_schema' ) ) {
                    $args   = $controller->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE );
                    $schema = $this->sanitize_args_to_schema( $args );

                    if ( ! $skip_ids ) {
                        $schema['properties']['id'] = array(
                            'type'        => 'integer',
                            'description' => __( 'Unique identifier for the resource', 'hostinger-ai-assistant' ),
                        );

                        if ( ! isset( $schema['required'] ) ) {
                            $schema['required'] = array();
                        }
                        if ( ! in_array( 'id', $schema['required'], true ) ) {
                            $schema['required'][] = 'id';
                        }
                    }

                    return $schema;
                }
                break;
            case 'get':
                if ( $skip_ids && method_exists( $controller, 'get_endpoint_args_for_item_schema' ) ) {
                    $args = $controller->get_endpoint_args_for_item_schema( WP_REST_Server::READABLE );
                    return $this->sanitize_args_to_schema( $args );
                }

                return array(
                    'type'       => 'object',
                    'properties' => array(
                        'id' => array(
                            'type'        => 'integer',
                            'description' => __( 'Unique identifier for the resource', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'id' ),
                );
            case 'delete':
                return array(
                    'type'       => 'object',
                    'properties' => array(
                        'id' => array(
                            'type'        => 'integer',
                            'description' => __( 'Unique identifier for the resource', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'id' ),
                );
            case 'report':
                return array(
                    'type'       => 'object',
                    'properties' => new stdClass(),
                );
        }

        return array(
            'type'       => 'object',
            'properties' => new stdClass(),
        );
    }

    private function remove_readonly_properties( array $properties ): array {
        $filtered = array();

        foreach ( $properties as $key => $property ) {
            if ( is_array( $property ) ) {
                if ( isset( $property['readonly'] ) && $property['readonly'] ) {
                    continue;
                }

                if ( isset( $property['properties'] ) && is_array( $property['properties'] ) ) {
                    $property['properties'] = $this->remove_readonly_properties( $property['properties'] );
                }

                if ( isset( $property['items']['properties'] ) && is_array( $property['items']['properties'] ) ) {
                    $property['items']['properties'] = $this->remove_readonly_properties( $property['items']['properties'] );
                }

                $filtered[ $key ] = $property;
            } else {
                $filtered[ $key ] = $property;
            }
        }

        return $filtered;
    }

    private function filter_complex_types_from_properties( array $properties ): array {
        foreach ( $properties as $key => $property ) {
            if ( is_array( $property ) ) {
                if ( isset( $property['type'] ) && is_array( $property['type'] ) ) {
                    $property['type'] = array_values(
                        array_filter(
                            $property['type'],
                            function ( $type ) {
                                return ! in_array( $type, array( 'object', 'array' ), true );
                            }
                        )
                    );

                    if ( count( $property['type'] ) === 1 ) {
                        $property['type'] = $property['type'][0];
                    } elseif ( empty( $property['type'] ) ) {
                        $property['type'] = 'string';
                    }
                }

                if ( isset( $property['properties'] ) && is_array( $property['properties'] ) ) {
                    $property['properties'] = $this->filter_complex_types_from_properties( $property['properties'] );
                }

                if ( isset( $property['items']['properties'] ) && is_array( $property['items']['properties'] ) ) {
                    $property['items']['properties'] = $this->filter_complex_types_from_properties( $property['items']['properties'] );
                }

                if ( $property['type'] === 'array' && empty( $property['items'] ) ) {
                    $property['items'] = array(
                        'type' => 'string',
                    );
                }

                $properties[ $key ] = $property;
            }
        }

        return $properties;
    }

    protected function sanitize_args_to_schema( array $args ): array {
        $properties = array();
        $required   = array();

        foreach ( $args as $key => $arg ) {
            if ( isset( $arg['format'] ) && $arg['format'] === 'date-time' ) {
                continue;
            }

            if ( isset( $arg['readonly'] ) && $arg['readonly'] ) {
                continue;
            }

            $property = array();

            if ( isset( $arg['type'] ) ) {
                $property['type'] = $arg['type'];
            }
            if ( isset( $arg['description'] ) ) {
                $property['description'] = $arg['description'];
            }
            if ( isset( $arg['default'] ) ) {
                $property['default'] = $arg['default'];
            }
            if ( isset( $arg['enum'] ) ) {
                $property['enum'] = array_values( $arg['enum'] );
            }
            if ( isset( $arg['items'] ) ) {
                $property['items'] = $arg['items'];
            }
            if ( isset( $arg['minimum'] ) ) {
                $property['minimum'] = $arg['minimum'];
            }
            if ( isset( $arg['maximum'] ) ) {
                $property['maximum'] = $arg['maximum'];
            }
            if ( isset( $arg['format'] ) ) {
                $property['format'] = $arg['format'];
            }
            if ( isset( $arg['properties'] ) ) {
                $property['properties'] = $arg['properties'];
            }

            if ( isset( $property['type'] ) && ! isset( $property['items'] ) ) {
                $types = is_array( $property['type'] ) ? $property['type'] : array( $property['type'] );
                if ( in_array( 'object', $types, true ) || in_array( 'array', $types, true ) ) {
                    $property['items'] = array(
                        'type' => array( 'string', 'number', 'boolean', 'integer', 'null' ),
                    );
                }
            }

            if ( isset( $property['type'] ) && is_array( $property['type'] ) && count( $property['type'] ) > 1 ) {
                if ( ! empty( $property['properties'] ) ) {
                    $property['type'] = 'object';
                } elseif ( isset( $property['items'] ) ) {
                    $property['type'] = 'array';
                }
            }

            if ( isset( $property['type'] ) ) {
                $types = is_array( $property['type'] ) ? $property['type'] : array( $property['type'] );

                if ( in_array( 'object', $types, true ) && ! in_array( 'array', $types, true ) ) {
                    unset( $property['items'] );
                }

                if ( in_array( 'array', $types, true ) && ! in_array( 'object', $types, true ) ) {
                    unset( $property['properties'] );
                }

                $primitive_types = array( 'string', 'boolean', 'integer', 'number', 'null' );
                $all_primitive   = ! empty( $types ) && count( array_intersect( $types, $primitive_types ) ) === count( $types );

                if ( $all_primitive ) {
                    unset( $property['properties'] );
                    unset( $property['items'] );
                }
            }

            if ( isset( $property['properties'] ) && is_array( $property['properties'] ) ) {
                $property['properties'] = $this->remove_readonly_properties( $property['properties'] );
            }

            if ( isset( $property['items']['properties'] ) && is_array( $property['items']['properties'] ) ) {
                $property['items']['properties'] = $this->remove_readonly_properties( $property['items']['properties'] );

                if ( $key === 'meta_data' ) {
                    $property['items']['properties'] = $this->filter_complex_types_from_properties( $property['items']['properties'] );
                }
            }

            if ( isset( $property['properties'] ) && is_array( $property['properties'] ) && $key === 'meta' ) {
                $property['properties'] = $this->filter_complex_types_from_properties( $property['properties'] );
            }

            if ( isset( $property['properties'] ) && is_array( $property['properties'] ) && empty( $property['properties'] ) ) {
                $property['properties'] = new stdClass();
            }

            if ( isset( $arg['required'] ) && true === $arg['required'] ) {
                $required[] = $key;
            }

            $properties[ $key ] = $property;
        }

        $schema = array(
            'type'       => 'object',
            'properties' => $properties,
        );

        if ( ! empty( $required ) ) {
            $schema['required'] = array_unique( $required );
        }

        return $schema;
    }

    protected function apply_schema_modifications( array $schema, array $modifications ): array {
        if ( isset( $schema['properties'] ) ) {
            if ( $schema['properties'] instanceof stdClass ) {
                $schema['properties'] = (array) $schema['properties'];
            }
        }

        if ( isset( $modifications['properties'] ) ) {
            foreach ( $modifications['properties'] as $key => $value ) {
                if ( isset( $schema['properties'][ $key ] ) ) {
                    $schema['properties'][ $key ] = array_merge( $schema['properties'][ $key ], $value );
                } else {
                    $schema['properties'][ $key ] = $value;
                }
            }
        }

        if ( isset( $modifications['required'] ) ) {
            $schema['required'] = array_unique( array_merge( $schema['required'] ?? array(), $modifications['required'] ) );
        }

        return $schema;
    }

    protected function apply_meta_modifications( array $meta, array $modifications ): array {
        return $this->array_merge_recursive_distinct( $meta, $modifications );
    }

    /**
     * Merges two arrays recursively, but does not overwrite numeric keys.
     */
    private function array_merge_recursive_distinct( array $array1, array $array2 ): array {
        $merged = $array1;

        foreach ( $array2 as $key => $value ) {
            if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
                if ( $this->is_indexed_array( $value ) || $this->is_indexed_array( $merged[ $key ] ) ) {
                    $merged[ $key ] = $value;
                } else {
                    $merged[ $key ] = $this->array_merge_recursive_distinct( $merged[ $key ], $value );
                }
            } else {
                $merged[ $key ] = $value;
            }
        }

        return $merged;
    }

    private function is_indexed_array( array $items ): bool {
        if ( empty( $items ) ) {
            return false;
        }
        return array_keys( $items ) === range( 0, count( $items ) - 1 );
    }

    private function remove_types_from_schema( array $schema, array $types_to_remove ): array {
        foreach ( $schema as $key => $value ) {
            if ( is_array( $value ) ) {
                $schema[ $key ] = $this->remove_types_from_schema( $value, $types_to_remove );
            } elseif ( $key === 'type' && in_array( $value, $types_to_remove, true ) ) {
                $schema[ $key ] = null;
            } elseif ( $key === 'format' && in_array( $value, $types_to_remove, true ) ) {
                unset( $schema[ $key ] );
                if ( ! isset( $schema['type'] ) ) {
                    $schema['type'] = null;
                }
            }
        }

        return $schema;
    }

    private function clean_output_schema( array $schema ): array {
        $keys_to_remove = array( 'required', 'context', 'arg_options' );

        foreach ( $schema as $key => $value ) {
            if ( in_array( $key, $keys_to_remove, true ) ) {
                unset( $schema[ $key ] );
            } elseif ( is_array( $value ) ) {
                if ( $key === 'properties' && empty( $value ) ) {
                    $schema[ $key ] = new stdClass();
                } else {
                    $schema[ $key ] = $this->clean_output_schema( $value );
                }
            }
        }

        return $schema;
    }

    protected function execute_operation( RestOperationConfig $config, array $input ): WP_Error|array {
        $operation = $config->get_operation();
        $route     = $config->get_route();
        $method    = $config->get_http_method_for_operation();

        $request_route = $route;
        if ( isset( $input['id'] ) && in_array( $operation, array( 'get', 'update', 'delete' ), true ) ) {
            $request_route .= '/' . intval( $input['id'] );
            unset( $input['id'] );
        }

        $request = new WP_REST_Request( $method, $request_route );
        foreach ( $input as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = $response instanceof WP_REST_Response ? $response->get_data() : $response;
        if ( in_array( $operation, array( 'list', 'report' ), true ) ) {
            return array( 'data' => $data );
        }

        return $data;
    }
}
