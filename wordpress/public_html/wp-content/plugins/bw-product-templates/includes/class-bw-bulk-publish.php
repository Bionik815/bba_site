<?php
/**
 * BW Bulk Publish — take imported/draft products live in batches.
 *
 * After a Wix import leaves hundreds of draft products, this publishes them
 * without 37 pages of native bulk-edit. Filter to one client (category) or
 * publish everything; excludes leftover "TEMPLATE …" blanks by default.
 * Runs as a batched AJAX loop with a progress bar so it never times out.
 */

if (!defined('ABSPATH')) {
    exit;
}

class BW_Bulk_Publish
{
    const PAGE_SLUG = 'bw-bulk-publish';
    const ACTION_START = 'bw_pub_start';
    const AJAX_BATCH = 'bw_pub_batch';
    const NONCE = 'bw_pub_nonce';
    const TRANSIENT = 'bw_pub_';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_post_' . self::ACTION_START, [$this, 'handle_start']);
        add_action('wp_ajax_' . self::AJAX_BATCH, [$this, 'ajax_batch']);
    }

    public function add_admin_page()
    {
        add_submenu_page(
            'edit.php?post_type=product',
            __('Bulk Publish', 'bw'),
            __('Bulk Publish', 'bw'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    /** Draft product IDs matching the chosen filters. */
    private function collect_ids($cat_id, $only_imported, $exclude_templates)
    {
        $args = [
            'post_type' => 'product',
            'post_status' => 'draft',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];
        if ($only_imported) {
            $args['meta_key'] = '_bw_wix_import';
            $args['meta_value'] = '1';
        }
        if ($cat_id > 0) {
            $args['tax_query'] = [['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => [$cat_id]]];
        }
        $ids = get_posts($args);

        if ($exclude_templates && $ids) {
            $ids = array_values(array_filter($ids, static function ($id) {
                if (get_post_meta($id, BW_Product_Templates::META_IS_TEMPLATE, true) === '1') {
                    return false;
                }
                return stripos((string) get_the_title($id), 'TEMPLATE ') !== 0;
            }));
        }

        return array_map('intval', $ids);
    }

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }
        $state = sanitize_text_field(wp_unslash($_GET['bwpub'] ?? ''));
        $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));

        if ($state === 'progress') {
            $data = $token ? get_transient(self::TRANSIENT . $token) : false;
            if (is_array($data)) {
                $this->render_progress($token, $data);
                return;
            }
        }

        $draft_total = count($this->collect_ids(0, true, true));
        $cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Bulk Publish', 'bw'); ?></h1>
            <p><?php
                printf(
                    esc_html__('%d imported draft products are ready to publish (excluding TEMPLATE blanks).', 'bw'),
                    (int) $draft_total
                );
            ?></p>

            <div class="card" style="padding:16px;max-width:640px">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field(self::NONCE, self::NONCE); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_START); ?>">
                    <p>
                        <label><strong><?php esc_html_e('Which products?', 'bw'); ?></strong></label><br>
                        <select name="cat_id" style="min-width:280px">
                            <option value="0"><?php esc_html_e('All client stores', 'bw'); ?></option>
                            <?php foreach ($cats as $cat) : ?>
                                <option value="<?php echo (int) $cat->term_id; ?>"><?php echo esc_html($cat->name); ?> (<?php echo (int) $cat->count; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p><label><input type="checkbox" name="only_imported" value="1" checked> <?php esc_html_e('Only Wix-imported products (recommended — leaves your other drafts alone)', 'bw'); ?></label></p>
                    <p><label><input type="checkbox" name="exclude_templates" value="1" checked> <?php esc_html_e('Skip TEMPLATE / template products', 'bw'); ?></label></p>
                    <?php submit_button(__('Publish products', 'bw')); ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function handle_start()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }
        check_admin_referer(self::NONCE, self::NONCE);

        $cat_id = absint($_POST['cat_id'] ?? 0);
        $only_imported = !empty($_POST['only_imported']);
        $exclude_templates = !empty($_POST['exclude_templates']);

        $ids = $this->collect_ids($cat_id, $only_imported, $exclude_templates);
        if (!$ids) {
            wp_safe_redirect(add_query_arg(['post_type' => 'product', 'page' => self::PAGE_SLUG, 'bwpub' => 'none'], admin_url('edit.php')));
            exit;
        }

        $token = wp_generate_password(12, false);
        set_transient(self::TRANSIENT . $token, ['ids' => $ids], HOUR_IN_SECONDS);

        wp_safe_redirect(add_query_arg(['post_type' => 'product', 'page' => self::PAGE_SLUG, 'bwpub' => 'progress', 'token' => $token], admin_url('edit.php')));
        exit;
    }

    private function render_progress($token, $data)
    {
        $total = count($data['ids']);
        ?>
        <div class="card" style="padding:16px;max-width:640px">
            <h2><?php esc_html_e('Publishing', 'bw'); ?></h2>
            <p><?php esc_html_e('Keep this tab open. Products publish in batches.', 'bw'); ?></p>
            <div style="background:#eee;border-radius:999px;height:22px;overflow:hidden">
                <div id="bw-pub-bar" style="background:#2271b1;height:100%;width:0;transition:width .2s"></div>
            </div>
            <p id="bw-pub-status" style="font-weight:600;margin-top:10px"><?php esc_html_e('Starting…', 'bw'); ?></p>
            <p id="bw-pub-done" style="display:none"><a class="button button-primary" href="<?php echo esc_url(admin_url('edit.php?post_type=product')); ?>"><?php esc_html_e('View published products', 'bw'); ?></a></p>
        </div>
        <script>
        (function () {
            var TOKEN = <?php echo wp_json_encode($token); ?>;
            var TOTAL = <?php echo (int) $total; ?>;
            var NONCE = <?php echo wp_json_encode(wp_create_nonce(self::AJAX_BATCH)); ?>;
            var AJAX = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var offset = 0, published = 0;
            var bar = document.getElementById('bw-pub-bar');
            var status = document.getElementById('bw-pub-status');

            function run() {
                var body = new URLSearchParams();
                body.set('action', <?php echo wp_json_encode(self::AJAX_BATCH); ?>);
                body.set('token', TOKEN);
                body.set('offset', offset);
                body.set('_nonce', NONCE);
                fetch(AJAX, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || !res.success) { status.textContent = 'Error: ' + ((res && res.data) || 'unknown'); return; }
                        offset = res.data.offset; published += res.data.published;
                        bar.style.width = (TOTAL ? Math.round(offset / TOTAL * 100) : 100) + '%';
                        status.textContent = offset + ' / ' + TOTAL + ' processed · ' + published + ' published';
                        if (res.data.done) {
                            status.textContent = 'Done — ' + published + ' products published.';
                            document.getElementById('bw-pub-done').style.display = 'block';
                        } else { run(); }
                    })
                    .catch(function (e) { status.textContent = 'Network error (retrying): ' + e.message; setTimeout(run, 2000); });
            }
            run();
        }());
        </script>
        <?php
    }

    public function ajax_batch()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('permission');
        }
        check_ajax_referer(self::AJAX_BATCH, '_nonce');

        $token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
        $offset = absint($_POST['offset'] ?? 0);
        $data = $token ? get_transient(self::TRANSIENT . $token) : false;
        if (!is_array($data) || empty($data['ids'])) {
            wp_send_json_error('expired');
        }

        @set_time_limit(0);
        $batch = 25;
        $slice = array_slice($data['ids'], $offset, $batch);
        $published = 0;
        foreach ($slice as $id) {
            if (get_post_type($id) !== 'product' || get_post_status($id) !== 'draft') {
                continue;
            }
            wp_update_post(['ID' => (int) $id, 'post_status' => 'publish']);
            wc_delete_product_transients((int) $id);
            $published++;
        }

        $new_offset = $offset + count($slice);
        wp_send_json_success([
            'offset' => $new_offset,
            'published' => $published,
            'done' => $new_offset >= count($data['ids']),
        ]);
    }
}

new BW_Bulk_Publish();
