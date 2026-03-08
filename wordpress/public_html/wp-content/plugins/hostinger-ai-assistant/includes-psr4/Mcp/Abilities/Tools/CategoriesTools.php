<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_REST_Terms_Controller;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class CategoriesTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai-assistant/categories-list',
                    'label'       => __( 'List Categories', 'hostinger-ai-assistant' ),
                    'description' => __( 'List all WordPress post categories with pagination and filtering.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'    => 'List Categories',
                            'readonly' => true,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai-assistant/categories-get',
                    'label'       => __( 'Get Category', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single WordPress category by ID. Returns the full category object with all fields.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'    => 'Get Category',
                            'readonly' => true,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai-assistant/categories-create',
                    'label'       => __( 'Create Category', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new WordPress post category. Requires a name.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'       => 'Add Category',
                            'readonly'    => false,
                            'destructive' => false,
                            'idempotent'  => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai-assistant/categories-update',
                    'label'       => __( 'Update Category', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing WordPress category by ID. Only provided fields will be updated.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'       => 'Update Category',
                            'readonly'    => false,
                            'destructive' => false,
                            'idempotent'  => true,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'                  => 'hostinger-ai-assistant/categories-delete',
                    'label'                      => __( 'Delete Category', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Delete a WordPress category by ID. This action cannot be undone.', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'force' => array(
                                'type'        => 'boolean',
                                'description' => __( 'Force category deletion', 'hostinger-ai-assistant' ),
                            ),
                        ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'           => 'Delete Category',
                            'readonly'        => false,
                            'destructive'     => true,
                            'destructiveHint' => true,
                            'idempotent'      => true,
                        ),
                    ),
                ),
            ),
            WP_REST_Terms_Controller::class,
            '/wp/v2/categories',
            'category'
        );
    }
}
