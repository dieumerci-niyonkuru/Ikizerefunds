<?php
// Lightweight loader — fades out fast so page appears instantly.
?>
<div id="page-loader" style="position:fixed;inset:0;z-index:9999;background:#16234B;display:flex;align-items:center;justify-content:center;transition:opacity .2s ease;">
    <img src="<?= e(APP_URL) ?>/<?= e($siteLogo) ?>" alt="" style="height:48px;width:48px;border-radius:10px;background:#fff;padding:6px;object-fit:contain;">
</div>
<noscript><style>#page-loader { display: none !important; }</style></noscript>
<script>
(function(){
    var l=document.getElementById('page-loader');
    if(!l)return;
    var h=function(){l.style.opacity='0';l.style.pointerEvents='none';setTimeout(function(){l.remove()},300);};
    if(document.readyState==='complete'){h();}else{window.addEventListener('load',h);}
    setTimeout(h,2000);
})();
</script>
