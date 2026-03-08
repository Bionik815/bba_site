<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_REST_Terms_Controller;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class TagsTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai-assistant/tags-list',
                    'label'       => __( 'List Tags', 'hostinger-ai-assistant' ),
                    'description' => __( 'List all WordPress post tags with pagination and filtering.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'    => 'List Tags',
                            'readonly' => true,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai-assistant/tags-get',
                    'label'       => __( 'Get Tag', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single WordPress tag by ID. Returns the full tag object with all fields.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'    => 'Get Tag',
                            'readonly' => true,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai-assistant/tags-create',
                    'label'       => __( 'Create Tag', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new WordPress post tag. Requires a name.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'       => 'Add Tag',
                            'readonly'    => false,
                            'destructive' => false,
                            'idempotent'  => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai-assistant/tags-update',
                    'label'       => __( 'Update Tag', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing WordPress tag by ID. Only provided fields will be updated.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'       => 'Update Tag',
                            'readonly'    => false,
                            'destructive' => false,
                            'idempotent'  => true,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'                  => 'hostinger-ai-assistant/tags-delete',
                    'label'                      => __( 'Delete Tag', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Delete a WordPress tag by ID. This action cannot be undone.', 'hostinger-ai-assistant' ),
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
                            'title'           => 'Delete Tag',
                            'readonly'        => false,
                            'destructive'     => true,
                            'destructiveHint' => true,
                            'idempotent'      => true,
                        ),
                    ),
                ),
            ),
            WP_REST_Terms_Controller::class,
            '/wp/v2/tags',
            'post_tag'
        );
    }
}
