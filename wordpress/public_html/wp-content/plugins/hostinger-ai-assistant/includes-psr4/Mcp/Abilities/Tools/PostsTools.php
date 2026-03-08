<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_REST_Posts_Controller;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class PostsTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai-assistant/posts-search',
                    'label'       => __( 'Search Posts', 'hostinger-ai-assistant' ),
                    'description' => __( 'Search and filter WordPress posts with pagination. Returns a list of posts matching the search criteria.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'    => 'Search Posts',
                            'readonly' => true,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai-assistant/posts-get',
                    'label'       => __( 'Get Post', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single WordPress post by ID. Returns the full post object with all fields.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'    => 'Get Post',
                            'readonly' => true,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai-assistant/posts-create',
                    'label'       => __( 'Create Post', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new WordPress post. Requires title and content.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'       => 'Add Post',
                            'readonly'    => false,
                            'destructive' => false,
                            'idempotent'  => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai-assistant/posts-update',
                    'label'       => __( 'Update Post', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing WordPress post by ID. Only provided fields will be updated.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'       => 'Update Post',
                            'readonly'    => false,
                            'destructive' => false,
                            'idempotent'  => true,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'   => 'hostinger-ai-assistant/posts-delete',
                    'label'       => __( 'Delete Post', 'hostinger-ai-assistant' ),
                    'description' => __( 'Delete a WordPress post by ID. This action cannot be undone.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Delete Post',
                            'readonly'        => false,
                            'destructive'     => true,
                            'destructiveHint' => true,
                            'idempotent'      => true,
                        ),
                    ),
                ),
            ),
            WP_REST_Posts_Controller::class,
            '/wp/v2/posts',
            'post'
        );
    }
}
