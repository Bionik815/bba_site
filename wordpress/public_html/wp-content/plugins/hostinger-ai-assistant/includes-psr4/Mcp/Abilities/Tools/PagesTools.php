<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_REST_Posts_Controller;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class PagesTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai-assistant/pages-search',
                    'label'       => __( 'Search Pages', 'hostinger-ai-assistant' ),
                    'description' => __( 'Search and filter WordPress pages with pagination. Returns a list of pages matching the search criteria.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'    => 'Search Pages',
                            'readonly' => true,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai-assistant/pages-get',
                    'label'       => __( 'Get Page', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single WordPress page by ID. Returns the full page object with all fields.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'    => 'Get Page',
                            'readonly' => true,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai-assistant/pages-create',
                    'label'       => __( 'Create Page', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new WordPress page. Requires title and content.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'       => 'Add Page',
                            'readonly'    => false,
                            'destructive' => false,
                            'idempotent'  => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai-assistant/pages-update',
                    'label'       => __( 'Update Page', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing WordPress page by ID. Only provided fields will be updated.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'       => 'Update Page',
                            'readonly'    => false,
                            'destructive' => false,
                            'idempotent'  => true,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'   => 'hostinger-ai-assistant/pages-delete',
                    'label'       => __( 'Delete Page', 'hostinger-ai-assistant' ),
                    'description' => __( 'Delete a WordPress page by ID. This action cannot be undone.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Delete Page',
                            'readonly'        => false,
                            'destructive'     => true,
                            'destructiveHint' => true,
                            'idempotent'      => true,
                        ),
                    ),
                ),
            ),
            WP_REST_Posts_Controller::class,
            '/wp/v2/pages',
            'page'
        );
    }
}
