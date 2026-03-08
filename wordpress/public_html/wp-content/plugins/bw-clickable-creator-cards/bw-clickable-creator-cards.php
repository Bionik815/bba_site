<?php
/**
 * Plugin Name: BW Clickable Creator Cards
 * Description: Makes bw_creator archive cards (group + all creators) fully clickable (article → link). Adds keyboard access and preserves inner links.
 * Version: 1.0.0
 * Author: You
 * License: GPL-2.0+
 */
if (!defined('ABSPATH')) exit;

class BW_Clickable_Creator_Cards {
  public function __construct(){
    add_action('wp_enqueue_scripts', [$this,'assets']);
  }
  public function assets(){
    // Only load on creator archives
    if (!is_tax('bw_group') && !is_post_type_archive('bw_creator')) return;

    // CSS: pointer cursor + focus outline
    $css = '
      .tax-bw_group article.type-bw_creator,
      .post-type-archive-bw_creator article.type-bw_creator{ cursor:pointer; }
      .bw-card-focus{ outline: 3px solid #2563eb; outline-offset: 2px; }
    ';
    wp_register_style('bw-card-click-css', false, [], '1.0.0');
    wp_add_inline_style('bw-card-click-css', $css);
    wp_enqueue_style('bw-card-click-css');

    // JS: turn each article into a link (but don’t hijack inner links)
    $js = <<<JS
(function(){
  var cards = document.querySelectorAll('.tax-bw_group article.type-bw_creator, .post-type-archive-bw_creator article.type-bw_creator');
  cards.forEach(function(card){
    var titleLink = card.querySelector('.entry-title a');
    if(!titleLink) return;

    // Make the whole card act like the title link
    card.setAttribute('role','link');
    card.setAttribute('tabindex','0');
    card.setAttribute('aria-label', titleLink.textContent.trim());

    function go(e){
      // If the click originated inside a real <a>, let it work normally
      var t = e.target;
      while(t && t !== card){
        if(t.tagName === 'A') return;
        t = t.parentElement;
      }
      // Navigate
      window.location.href = titleLink.getAttribute('href');
    }

    card.addEventListener('click', go);
    card.addEventListener('keydown', function(e){
      // Enter or Space triggers navigation for keyboard users
      if(e.key === 'Enter' || e.key === ' '){
        e.preventDefault();
        go(e);
      }
    });

    // Nice focus outline when tabbed
    card.addEventListener('focus', function(){ card.classList.add('bw-card-focus'); }, true);
    card.addEventListener('blur',  function(){ card.classList.remove('bw-card-focus'); }, true);
  });
})();
JS;
    wp_register_script('bw-card-click-js', false, [], '1.0.0', true);
    wp_add_inline_script('bw-card-click-js', $js);
    wp_enqueue_script('bw-card-click-js');
  }
}
new BW_Clickable_Creator_Cards();
