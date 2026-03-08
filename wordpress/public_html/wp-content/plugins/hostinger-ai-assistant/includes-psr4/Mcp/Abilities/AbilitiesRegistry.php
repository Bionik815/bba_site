<?php

namespace Hostinger\AiAssistant\Mcp\Abilities;

use Hostinger\AiAssistant\Mcp\Abilities\Tools\CategoriesTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\CustomPostTypesTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\ElementorTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\HostingerPluginTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\LiteSpeedCacheTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\MediaTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\PagesTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\PostsTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\SettingsTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\SiteInfoTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\TagsTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\UsersTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\WooOrdersTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\WooProductsTools;
use Hostinger\AiAssistant\Mcp\Abilities\Resources\PluginsInfoResource;
use Hostinger\AiAssistant\Mcp\Abilities\Resources\SiteInfoResource;
use Hostinger\AiAssistant\Mcp\Abilities\Resources\SiteSettingsResource;
use Hostinger\AiAssistant\Mcp\Abilities\Resources\ThemeInfoResource;
use Hostinger\AiAssistant\Mcp\Abilities\Resources\UsersInfoResource;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class AbilitiesRegistry {
    private array $tools = array(
        PostsTools::class,
        PagesTools::class,
        CategoriesTools::class,
        TagsTools::class,
        UsersTools::class,
        CustomPostTypesTools::class,
        MediaTools::class,
        SettingsTools::class,
        SiteInfoTools::class,
        WooProductsTools::class,
        WooOrdersTools::class,
        HostingerPluginTools::class,
        LiteSpeedCacheTools::class,
        ElementorTools::class,
    );

    private array $resources = array(
        SiteInfoResource::class,
        SiteSettingsResource::class,
        PluginsInfoResource::class,
        ThemeInfoResource::class,
        UsersInfoResource::class,
    );

    public function init(): void {
        $this->register_categories();
        add_action( 'wp_abilities_api_init', array( $this, 'register_tools' ) );
        add_action( 'wp_abilities_api_init', array( $this, 'register_resources' ) );
    }

    public function register_tools(): void {
        $this->register_abilities( $this->tools );
    }

    public function register_resources(): void {
        $this->register_abilities( $this->resources );
    }

    public function register_categories(): void {
        add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
    }

    public function register_category(): void {
        wp_register_ability_category(
            'hostinger-ai-assistant',
            array(
                'label'       => __( 'Hostinger AI Abilities', 'hostinger-ai-assistant' ),
                'description' => __( 'Abilities that provide a way of interacting with WordPress site.', 'hostinger-ai-assistant' ),
            )
        );
    }

    private function register_abilities( array $abilities ): void {
        if ( empty( $abilities ) ) {
            return;
        }

        foreach ( $abilities as $ability ) {
            $item = new $ability();

            if ( method_exists( $item, 'register' ) ) {
                $item->register();
            }
        }
    }
}
