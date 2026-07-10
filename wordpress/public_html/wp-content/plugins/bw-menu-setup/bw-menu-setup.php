<?php
/**
 * Plugin Name: BW Menu Setup
 * Description: Bulk-assign clients (creators) to CLIENT STORES menu groups and toggle which appear in the menu. Guesses each client's group from its name; you confirm and save in one pass. Built for after a Wix import creates many ungrouped clients.
 * Version: 1.0.0
 * Author: Barebones Apparel
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

class BW_Menu_Setup
{
    const CPT_CREATOR = 'bw_creator';
    const TAX_GROUP = 'bw_group';
    const PM_SHOW = '_bw_creator_show';
    const PM_ORDER = '_bw_creator_order';
    const PM_PINNED = '_bw_creator_pinned';
    const META_CREATOR_CAT = '_bw_creator_wc_category';
    const PAGE_SLUG = 'bw-menu-setup';
    const ACTION = 'bw_menu_setup_save';
    const ACTION_LOGOS = 'bw_menu_fill_logos';
    const NONCE = 'bw_menu_setup_nonce';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_post_' . self::ACTION, [$this, 'handle_save']);
        add_action('admin_post_' . self::ACTION_LOGOS, [$this, 'handle_fill_logos']);
    }

    public function add_admin_page()
    {
        add_submenu_page(
            'edit.php?post_type=' . self::CPT_CREATOR,
            __('Menu Setup', 'bw'),
            __('Menu Setup', 'bw'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    /** Guess: [group_slug_or_'', show_bool]. Junk/meta names default to hidden. */
    private function guess($name)
    {
        $l = strtolower($name);

        $junk = ['new', 'test', 'test client co', 'plain clothing', 'kids apparel', 'baseball tees', 'classic bba', 'classic hoodies'];
        foreach (['old_', 'hp ', 'template', 'baseball tees', 'classic ', 'plain clothing', 'kids apparel'] as $needle) {
            if (strpos($l, $needle) === 0 || strpos($l, $needle) !== false && in_array($l, $junk, true)) {
                return ['', false];
            }
        }
        if (in_array($l, $junk, true)) {
            return ['', false];
        }

        $has = static function ($needles) use ($l) {
            foreach ((array) $needles as $n) {
                if (strpos($l, $n) !== false) {
                    return true;
                }
            }
            return false;
        };

        if ($has(['barebones', 'country', 'fitness', 'dirt made', 'simple mind', 'breast cancer', 'skulls', 'not done yet', 'sui generis', 'brewery', 'bone dry', 'mythical'])) {
            return ['barebones-apparel', true];
        }
        if ($has(['school', 'elementary', 'middle', 'high school', 'mustang', 'knight', 'marauder', 'del sur', 'hillview', 'new vista', 'joshua', 'lincoln', 'barrel springs', 'mariposa', 'fulton', 'alsbury', 'jack northrop', 'desert view', 'newhall', 'avhs', 'lancaster high', 'grand arts', 'dance', 'boosters', 'playerpack'])) {
            return ['schools', true];
        }
        if ($has(['dj ', ' dj', 'go dj', 'joei', 'music'])) {
            return ['music', true];
        }
        if ($has(['axe n dagger', 'happy hours', 'salon', ' llc', ' inc'])) {
            return ['businesses', true];
        }
        // Everything else = athletes (individual names, clubs, sports).
        return ['athletes', true];
    }

    private function groups()
    {
        $terms = get_terms(['taxonomy' => self::TAX_GROUP, 'hide_empty' => false, 'orderby' => 'name']);
        return is_wp_error($terms) ? [] : $terms;
    }

    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }

        $creators = get_posts([
            'post_type' => self::CPT_CREATOR,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $groups = $this->groups();
        $notice = sanitize_text_field(wp_unslash($_GET['bwms'] ?? ''));

        // Current stats.
        $in_menu = 0;
        $no_logo = 0;
        foreach ($creators as $c) {
            $terms = wp_get_object_terms($c->ID, self::TAX_GROUP, ['fields' => 'ids']);
            $show = get_post_meta($c->ID, self::PM_SHOW, true) === '1';
            if ($terms && $show) {
                $in_menu++;
            }
            if (!get_post_thumbnail_id($c->ID)) {
                $no_logo++;
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Menu Setup', 'bw'); ?></h1>
            <p><?php esc_html_e('Put every client into the CLIENT STORES menu. Set each one\'s group and whether it shows. Guesses are pre-filled — review and save.', 'bw'); ?></p>

            <?php if ($notice === 'saved') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Menu updated.', 'bw'); ?></p></div>
            <?php elseif ($notice === 'logos') : ?>
                <div class="notice notice-success is-dismissible"><p><?php
                    printf(esc_html__('Filled %d client menu logos from their product photos. Replace with real logos anytime by setting a Featured Image on the client.', 'bw'), (int) ($_GET['filled'] ?? 0));
                ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:8px 0 16px">
                <?php wp_nonce_field(self::NONCE, self::NONCE); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_LOGOS); ?>">
                <button type="submit" class="button"><?php esc_html_e('Fill missing logos from product photos', 'bw'); ?></button>
                <span class="description"><?php esc_html_e('Gives every logo-less client a menu image from their first product, as a placeholder until you upload a real logo.', 'bw'); ?></span>
            </form>

            <p>
                <strong><?php echo (int) count($creators); ?></strong> <?php esc_html_e('clients ·', 'bw'); ?>
                <strong><?php echo (int) $in_menu; ?></strong> <?php esc_html_e('currently in the menu ·', 'bw'); ?>
                <strong><?php echo (int) $no_logo; ?></strong> <?php esc_html_e('without a logo (menu shows their name as text until you add one)', 'bw'); ?>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(self::NONCE, self::NONCE); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">

                <p>
                    <button type="button" class="button" id="bwms-apply-guesses"><?php esc_html_e('Apply all guesses', 'bw'); ?></button>
                    <button type="button" class="button" id="bwms-show-all"><?php esc_html_e('Show all', 'bw'); ?></button>
                    <button type="button" class="button" id="bwms-hide-all"><?php esc_html_e('Hide all', 'bw'); ?></button>
                    <input type="search" id="bwms-filter" placeholder="<?php esc_attr_e('Filter clients…', 'bw'); ?>" style="min-width:220px;margin-left:8px">
                </p>

                <table class="widefat striped">
                    <thead><tr>
                        <th><?php esc_html_e('Show', 'bw'); ?></th>
                        <th title="<?php esc_attr_e('Pin to the top of its group (for promoting an event or person)', 'bw'); ?>">📌 <?php esc_html_e('Pin', 'bw'); ?></th>
                        <th><?php esc_html_e('Client', 'bw'); ?></th>
                        <th><?php esc_html_e('Group', 'bw'); ?></th>
                        <th><?php esc_html_e('Logo', 'bw'); ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($creators as $c) :
                            $cur_terms = wp_get_object_terms($c->ID, self::TAX_GROUP);
                            $cur_group = ($cur_terms && !is_wp_error($cur_terms)) ? $cur_terms[0]->slug : '';
                            $cur_show = get_post_meta($c->ID, self::PM_SHOW, true) === '1';
                            [$guess_group, $guess_show] = $this->guess($c->post_title);
                            // Use existing values where set, else the guess.
                            $group_val = $cur_group ?: $guess_group;
                            $show_val = $cur_terms || $cur_show ? $cur_show : $guess_show;
                            $has_logo = (bool) get_post_thumbnail_id($c->ID);
                            $pinned = get_post_meta($c->ID, self::PM_PINNED, true) === '1';
                            ?>
                            <tr class="bwms-row" data-name="<?php echo esc_attr(strtolower($c->post_title)); ?>">
                                <td><input type="checkbox" class="bwms-show" name="show[<?php echo (int) $c->ID; ?>]" value="1" <?php checked($show_val); ?>></td>
                                <td><input type="checkbox" class="bwms-pin" name="pin[<?php echo (int) $c->ID; ?>]" value="1" <?php checked($pinned); ?>></td>
                                <td><a href="<?php echo esc_url(get_edit_post_link($c->ID)); ?>" target="_blank"><?php echo esc_html($c->post_title); ?></a></td>
                                <td>
                                    <select class="bwms-group" name="group[<?php echo (int) $c->ID; ?>]"
                                        data-guess="<?php echo esc_attr($guess_group); ?>">
                                        <option value=""><?php esc_html_e('— none —', 'bw'); ?></option>
                                        <?php foreach ($groups as $g) : ?>
                                            <option value="<?php echo esc_attr($g->slug); ?>" <?php selected($group_val, $g->slug); ?>><?php echo esc_html($g->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><?php echo $has_logo ? '✅' : '<span style="color:#b26a00">—</span>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top:14px"><button type="submit" class="button button-primary button-hero"><?php esc_html_e('Save menu setup', 'bw'); ?></button></p>
            </form>
        </div>
        <script>
        (function () {
            document.getElementById('bwms-apply-guesses').addEventListener('click', function () {
                document.querySelectorAll('.bwms-group').forEach(function (s) { s.value = s.getAttribute('data-guess') || ''; });
            });
            document.getElementById('bwms-show-all').addEventListener('click', function () {
                document.querySelectorAll('.bwms-show').forEach(function (c) { c.checked = true; });
            });
            document.getElementById('bwms-hide-all').addEventListener('click', function () {
                document.querySelectorAll('.bwms-show').forEach(function (c) { c.checked = false; });
            });
            document.getElementById('bwms-filter').addEventListener('input', function () {
                var q = this.value.toLowerCase();
                document.querySelectorAll('.bwms-row').forEach(function (r) {
                    r.style.display = r.getAttribute('data-name').indexOf(q) > -1 ? '' : 'none';
                });
            });
        }());
        </script>
        <?php
    }

    public function handle_save()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }
        check_admin_referer(self::NONCE, self::NONCE);

        $groups = (array) ($_POST['group'] ?? []);
        $show = (array) ($_POST['show'] ?? []);
        $pin = (array) ($_POST['pin'] ?? []);

        // Map group slug -> term_id once.
        $slug_to_id = [];
        foreach ($this->groups() as $g) {
            $slug_to_id[$g->slug] = (int) $g->term_id;
        }

        foreach ($groups as $creator_id => $slug) {
            $creator_id = absint($creator_id);
            if (!$creator_id || get_post_type($creator_id) !== self::CPT_CREATOR) {
                continue;
            }
            $slug = sanitize_title((string) $slug);
            if ($slug !== '' && isset($slug_to_id[$slug])) {
                wp_set_object_terms($creator_id, [$slug_to_id[$slug]], self::TAX_GROUP, false);
            } else {
                wp_set_object_terms($creator_id, [], self::TAX_GROUP, false);
            }

            $is_shown = !empty($show[$creator_id]);
            update_post_meta($creator_id, self::PM_SHOW, $is_shown ? '1' : '0');
            update_post_meta($creator_id, self::PM_PINNED, !empty($pin[$creator_id]) ? '1' : '0');
            if (get_post_meta($creator_id, self::PM_ORDER, true) === '') {
                update_post_meta($creator_id, self::PM_ORDER, 10);
            }
        }

        wp_safe_redirect(add_query_arg([
            'post_type' => self::CPT_CREATOR,
            'page' => self::PAGE_SLUG,
            'bwms' => 'saved',
        ], admin_url('edit.php')));
        exit;
    }

    /**
     * Give logo-less clients a menu image using their first product's photo,
     * so the menu is visual immediately. Real logos (a Featured Image on the
     * client) always win — this only fills the blanks.
     */
    public function handle_fill_logos()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }
        check_admin_referer(self::NONCE, self::NONCE);

        $creators = get_posts([
            'post_type' => self::CPT_CREATOR,
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);

        $filled = 0;
        foreach ($creators as $creator) {
            if (get_post_thumbnail_id($creator->ID)) {
                continue; // already has a logo
            }
            $slug = get_post_meta($creator->ID, self::META_CREATOR_CAT, true);
            if ($slug === '') {
                $slug = sanitize_title($creator->post_name ?: $creator->post_title);
            }
            $term = get_term_by('slug', $slug, 'product_cat');
            if (!$term || is_wp_error($term)) {
                continue;
            }

            $product_ids = get_posts([
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => 15,
                'fields' => 'ids',
                'tax_query' => [['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => [$term->term_id]]],
            ]);
            foreach ($product_ids as $pid) {
                $thumb = get_post_thumbnail_id($pid);
                if ($thumb) {
                    set_post_thumbnail($creator->ID, (int) $thumb);
                    update_post_meta($creator->ID, '_bw_logo_placeholder', '1');
                    $filled++;
                    break;
                }
            }
        }

        wp_safe_redirect(add_query_arg([
            'post_type' => self::CPT_CREATOR,
            'page' => self::PAGE_SLUG,
            'bwms' => 'logos',
            'filled' => $filled,
        ], admin_url('edit.php')));
        exit;
    }
}

new BW_Menu_Setup();
