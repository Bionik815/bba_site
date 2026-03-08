(function(){
  document.querySelectorAll('.bwsp-row').forEach(function(row){
    const track = row.querySelector('.bwsp-caro');
    const prev  = row.querySelector('.bwsp-nav.prev');
    const next  = row.querySelector('.bwsp-nav.next');
    if(!track || !prev || !next) return;

    function sync(){
      const max = track.scrollWidth - track.clientWidth - 1;
      prev.disabled = (track.scrollLeft <= 1);
      next.disabled = (track.scrollLeft >= max);
    }
    function jump(dir){
      const card = track.querySelector('.bwsp-card');
      const step = card ? card.getBoundingClientRect().width * 2 + 28 : track.clientWidth * 0.8;
      track.scrollBy({ left: dir * step, behavior: 'smooth' });
    }

    prev.addEventListener('click', ()=> jump(-1));
    next.addEventListener('click', ()=> jump(1));
    track.addEventListener('scroll', sync, { passive:true });

    setTimeout(sync, 50);
    window.addEventListener('resize', sync);
  });
})();
