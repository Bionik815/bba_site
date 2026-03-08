<?php

namespace Hostinger\AiAssistant\Mcp;

use Hostinger\AiAssistant\Functions;
use Hostinger\AiAssistant\Mcp\Rest\JwtAuth;
use Throwable;
use WP\MCP\Core\McpAdapter;
use WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
use WP\MCP\Transport\HttpTransport;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class McpServer {
    public const MCP_SERVER_VERSION = '1.0.0';
    private JwtAuth $jwt_auth;

    public function __construct( JwtAuth $jwt_auth ) {
        $this->jwt_auth = $jwt_auth;
    }
    public function init(): void {
        McpAdapter::instance();

        add_action( 'mcp_adapter_init', array( $this, 'create_server' ) );
    }

    public function create_server( McpAdapter $adapter ): void {
        $tools     = $this->get_mcp_abilities_by_type( 'tool' );
        $resources = $this->get_mcp_abilities_by_type( 'resource' );

        try {
            $adapter->create_server(
                'hostinger-ai-assistant-mcp-server',
                HOSTINGER_AI_ASSISTANT_REST_API_BASE,
                'mcp',
                'Hostinger AI Assistant MCP Server',
                __( 'Custom MCP Server for Hostinger AI Assistant.', 'hostinger-ai-assistant' ),
                self::MCP_SERVER_VERSION,
                array( HttpTransport::class ),
                ErrorLogMcpErrorHandler::class,
                NullMcpObservabilityHandler::class,
                $tools,
                $resources,
                array(),
                array( $this, 'authenticate_request' ),
            );
        } catch ( Throwable $e ) {
            Functions::log_event( 'MCP server init failed: ' . $e->getMessage() );
        }
    }


    public function authenticate_request( WP_REST_Request $request ): WP_Error|bool {
        $api_key = $request->get_header( 'X-Hostinger-JWT-Token' );

        if ( empty( $api_key ) ) {
            return new WP_Error(
                'missing_api_key',
                __( 'API key required', 'hostinger-ai-assistant' ),
                array( 'status' => 401 )
            );
        }

        if ( ! $this->jwt_auth->validate_jwt_token( $api_key ) ) {
            return new WP_Error(
                'invalid_api_key',
                __( 'Invalid API key', 'hostinger-ai-assistant' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    private function get_mcp_abilities_by_type( string $resource_type ): array {
        $all_abilities = wp_get_abilities();
        $mcp_resources = array();

        foreach ( $all_abilities as $ability ) {
            $meta = $ability->get_meta();

            if ( ! empty( $meta['mcp']['public'] ) ) {
                $mcp_type = $meta['mcp']['type'] ?? '';

                if ( $resource_type === $mcp_type ) {
                    $mcp_resources[] = $ability->get_name();
                }
            }
        }

        return $mcp_resources;
    }
}
