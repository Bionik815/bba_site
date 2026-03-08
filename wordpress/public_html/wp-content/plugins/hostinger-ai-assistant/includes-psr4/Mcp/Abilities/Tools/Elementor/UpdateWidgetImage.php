<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class UpdateWidgetImage extends BaseElementorTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai-assistant/elementor-update-widget-image',
            array(
                'label'               => __( 'Update Elementor Widget Image', 'hostinger-ai-assistant' ),
                'description'         => __( 'Updates image widget source URLs safely. Supports updating URL, media library ID, and alt text.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_id'   => array(
                            'type'        => 'integer',
                            'description' => __( 'The ID of the post/page containing the widget', 'hostinger-ai-assistant' ),
                        ),
                        'widget_id' => array(
                            'type'        => 'string',
                            'description' => __( 'The unique ID of the image widget to update', 'hostinger-ai-assistant' ),
                        ),
                        'image_url' => array(
                            'type'        => 'string',
                            'description' => __( 'The new image URL', 'hostinger-ai-assistant' ),
                        ),
                        'image_id'  => array(
                            'type'        => 'integer',
                            'description' => __( 'Optional: WordPress media library ID', 'hostinger-ai-assistant' ),
                        ),
                        'alt_text'  => array(
                            'type'        => 'string',
                            'description' => __( 'Optional: Image alt text for accessibility', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'post_id', 'widget_id', 'image_url' ),
                ),
                'execute_callback'    => array( $this, 'execute' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => $this->type,
                    ),
                    'annotations'  => array(
                        'title'       => 'Update Widget Image',
                        'readonly'    => false,
                        'destructive' => false,
                        'idempotent'  => true,
                    ),
                ),
            )
        );
    }

    public function execute( array $input ): array {
        $post_id   = $input['post_id'];
        $widget_id = $input['widget_id'];
        $image_url = $input['image_url'];
        $image_id  = $input['image_id'] ?? null;
        $alt_text  = $input['alt_text'] ?? null;

        $result = $this->get_elementor_data( $post_id );
        if ( isset( $result['error_code'] ) ) {
            return $result;
        }

        $elementor_data = $result['data'];

        $updated = false;
        $old_url = '';

        $this->update_widget_in_tree(
            $elementor_data,
            $widget_id,
            function ( &$widget ) use ( $image_url, $image_id, $alt_text, &$updated, &$old_url ) {
                $widget_type = $widget['widgetType'] ?? '';
                if ( $widget_type !== 'image' ) {
                    return array(
                        'error_code' => 'NOT_IMAGE_WIDGET',
                        'message'    => "Widget is type '{$widget_type}', not 'image'",
                    );
                }

                $old_url = $widget['settings']['image']['url'] ?? '';

                if ( ! isset( $widget['settings']['image'] ) ) {
                    $widget['settings']['image'] = array();
                }

                $widget['settings']['image']['url'] = $image_url;

                if ( $image_id !== null ) {
                    $widget['settings']['image']['id'] = $image_id;
                }

                if ( $alt_text !== null ) {
                    $widget['settings']['image_alt'] = $alt_text;
                }

                $updated = true;

                return null;
            }
        );

        if ( ! $updated ) {
            return array(
                'success'    => false,
                'error_code' => 'WIDGET_NOT_FOUND',
                /* translators: %s: widget id  */
                'message'    => __( 'Widget with ID %s not found', 'hostinger-ai-assistant' ),
            );
        }

        $save_result = $this->save_elementor_data( $post_id, $elementor_data );
        if ( ! $save_result['success'] ) {
            return $save_result;
        }

        return array(
            'success'   => true,
            'widget_id' => $widget_id,
            'old_url'   => $old_url,
            'new_url'   => $image_url,
        );
    }
}
