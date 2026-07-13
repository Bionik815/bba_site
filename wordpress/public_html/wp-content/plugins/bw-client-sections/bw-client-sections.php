<?php
/**
 * Plugin Name: BW Client Sections
 * Description: Shortcode to show client product sections (tabs or stacked) filtered by tags, scoped to a client category. Example: [bw_client_sections client="axe-n-dagger" sections="Jerseys:jersey|Jackets:jacket|Hoodies:hoodie|All:all" per="8" columns="4" layout="tabs"]
 * Version: 1.1.0
 */

if (!defined('ABSPATH')) exit;

class BW_Client_Sections {
  public function __construct(){
    add_shortcode('bw_client_sections', [$this,'shortcode']);
    add_action('wp_enqueue_scripts', [$this,'assets']);
  }

  public function assets(){
    $css = <<<CSS
/* tabs / pills */
.bwcs{margin:12px 0 24px}
.bwcs-nav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px;padding:0;list-style:none}
.bwcs-nav button{appearance:none;border:1px solid #e6e8ef;background:#f7f8fb;color:#111;padding:8px 12px;border-radius:999px;cursor:pointer;font-weight:700}
.bwcs-nav button[aria-selected="true"]{background:#111;color:#fff;border-color:#111}
.bwcs-panel{display:none}
.bwcs-panel.is-active{display:block}
/* product card polish works with Astra/Woo default */
.woocommerce ul.products li.product{border:1px solid #e7e7ea;border-radius:12px;overflow:hidden;background:#fff;padding:0 0 10px!important;transition:box-shadow .18s,transform .18s;display:flex;flex-direction:column;}
.woocommerce ul.products li.product:hover{box-shadow:0 12px 30px rgba(0,0,0,.08);transform:translateY(-2px);}
/* Catalog images are 1000x1000 squares — show the WHOLE image, never crop */
.woocommerce ul.products li.product a img{aspect-ratio:1/1;width:100%;height:auto;object-fit:contain;background:#fff;display:block;}
/* .astra-shop-summary-wrap in the selector out-guns Astra's padding reset */
.woocommerce ul.products li.product .astra-shop-summary-wrap .woocommerce-loop-product__title{font-size:1rem;padding:10px 12px 4px;margin:0;}
.woocommerce ul.products li.product .astra-shop-summary-wrap .ast-woo-product-category{padding:0 12px;}
.woocommerce ul.products li.product .price{padding:0 12px 8px;display:block;font-weight:700;}
/* Equal-height cards: summary column stretches, button pins to the bottom */
.woocommerce ul.products li.product .astra-shop-summary-wrap{display:flex;flex-direction:column;flex:1 1 auto;}
.woocommerce ul.products li.product .button{width:calc(100% - 24px);text-align:center;border-radius:8px;}
/* auto side margins CENTER the button no matter what the theme sets; auto top pins it to the card bottom */
.woocommerce ul.products li.product .astra-shop-summary-wrap .button{margin:auto auto 0!important;}
CSS;
    // Own handle: 'woocommerce-inline' is not reliably enqueued on every page,
    // which silently dropped these styles (the Customizer CSS copy used to
    // paper over that — removed 2026-07-12, this is now the single source).
    wp_register_style('bw-client-sections', false, [], '1.1.0');
    wp_enqueue_style('bw-client-sections');
    wp_add_inline_style('bw-client-sections', $css);

    $js = <<<JS
(function(){
  function init(root){
    var tabs = root.querySelectorAll('.bwcs-nav button');
    if(!tabs.length) return;
    function activate(id){
      tabs.forEach(b=>b.setAttribute('aria-selected', b.dataset.for===id ? 'true' : 'false'));
      root.querySelectorAll('.bwcs-panel').forEach(p=>p.classList.toggle('is-active', p.id===id));
    }
    tabs.forEach(b=>b.addEventListener('click', function(e){ e.preventDefault(); activate(this.dataset.for); }));
    // default: first tab active
    activate(tabs[0].dataset.for);
  }
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.bwcs').forEach(init);
  });
})();
JS;
    wp_add_inline_script('jquery-core', $js); // loads on frontend anyway
  }

  public function shortcode($atts){
    $a = shortcode_atts([
      // required: the client category slug that all products belong to
      'client'   => '',                       // e.g., axe-n-dagger
      // sections: "Label:tag|Label 2:tag2|All:all"
      'sections' => 'Jerseys:jersey|Jackets:jacket|Hoodies:hoodie|All:all',
      'per'      => '8',
      'columns'  => '4',
      'order'    => 'DESC',
      'orderby'  => 'date',
      // layout: tabs | sections
      'layout'   => 'tabs',
    ], $atts, 'bw_client_sections');

    $client = sanitize_title($a['client']);
    if (!$client) return '<!-- bwcs: missing client slug -->';

    // Parse sections string
    $pairs = array_filter(array_map('trim', explode('|', $a['sections'])));
    if (!$pairs) return '<!-- bwcs: no sections -->';

    $uid = 'bwcs-' . wp_generate_uuid4();
    ob_start();

    echo '<section class="bwcs" id="'.esc_attr($uid).'">';

    if ($a['layout'] === 'tabs') {
      echo '<div class="bwcs-nav" role="tablist" aria-label="Product sections">';
      $i=0;
      foreach ($pairs as $pair){
        [$label,$tag] = array_pad(array_map('trim', explode(':',$pair,2)), 2, '');
        if ($label==='') continue;
        $panel_id = $uid.'-'. ($tag!=='' ? sanitize_title($tag) : 'all-'.$i);
        echo '<button type="button" role="tab" aria-selected="false" data-for="'.esc_attr($panel_id).'">'.esc_html($label).'</button>';
        $i++;
      }
      echo '</div>';
    }

    // Panels/sections
    $i=0;
    foreach ($pairs as $pair){
      [$label,$tag] = array_pad(array_map('trim', explode(':',$pair,2)), 2, '');
      if ($label==='') continue;

      $panel_id = $uid.'-'. ($tag!=='' ? sanitize_title($tag) : 'all-'.$i);
      $classes = 'bwcs-panel';
      if ($a['layout'] !== 'tabs') $classes .= ' is-active'; // always visible in sections layout

      echo '<div id="'.esc_attr($panel_id).'" class="'.esc_attr($classes).'" role="tabpanel" aria-labelledby="'.esc_attr($panel_id).'-tab">';

      // Build Woo [products] shortcode
      $args = [
        'category'  => $client,               // always scope to client category
        'limit'     => (int)$a['per'],
        'columns'   => (int)$a['columns'],
        'orderby'   => sanitize_key($a['orderby']),
        'order'     => ($a['order']==='ASC'?'ASC':'DESC'),
        'visibility'=> 'visible',
      ];

      // Tag handling
      $tag = strtolower($tag);
      if ($tag && $tag !== 'all') {
        $args['tag'] = sanitize_title($tag);
      }

      // Build [products ...] string
      $parts = [];
      foreach ($args as $k=>$v) $parts[] = $k.'="'.esc_attr($v).'"';
      $shortcode = '[products '.implode(' ', $parts).']';

      echo do_shortcode($shortcode);

      echo '</div>';
      $i++;
    }

    echo '</section>';

    return ob_get_clean();
  }
}
new BW_Client_Sections();
