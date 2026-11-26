/* 現場3D 工程表：工事の工期（開始日・工期末）の入力欄。値は window.GENBA3D_TERM（サーバーが差し込む）。保存すると読み込み直して工程表に反映する。 */
(function(){
  var T=window.GENBA3D_TERM; if(!T)return;
  var $=function(id){return document.getElementById(id);};
  var st=document.createElement('style');
  st.textContent='.g3term{margin:6px 0 14px;padding:10px 12px;border:1px solid var(--line,#d3dae0);border-left:4px solid var(--accent,#c2313a);background:var(--panel,#fff);font-size:13px;line-height:1.5}'
    +'.g3term .r{display:flex;flex-wrap:wrap;align-items:flex-end;gap:8px 14px}.g3term label{display:flex;flex-direction:column;gap:2px;font-size:12px;color:var(--muted,#5b6b78)}'
    +'.g3term input{font:inherit;font-size:14px;padding:4px 6px;border:1px solid var(--line,#ccd3da);border-radius:3px;background:#fff;color:#17232e}'
    +'.g3term button{font:inherit;font-size:13px;padding:5px 14px;border:1px solid #17232e;border-radius:3px;background:#17232e;color:#fff;cursor:pointer}.g3term button[disabled]{opacity:.45;cursor:default}'
    +'.g3term .t{font-weight:600;font-size:14px;color:var(--ink,#17232e);align-self:center}.g3term .d{align-self:center;color:var(--muted,#5b6b78);font-size:12px}'
    +'.g3term .m{margin-top:6px;font-size:12px;color:var(--muted,#5b6b78)}.g3term .m.err{color:#b91c1c}.g3term .m.ok{color:#15803d}'
    +'.g3term details{margin-top:6px;font-size:12px;color:var(--muted,#5b6b78)}.g3term summary{cursor:pointer;width:fit-content}.g3term ol{margin:4px 0 0;padding-left:1.2em}.g3term li{margin:2px 0}.g3term li b{font-weight:600;color:var(--ink,#17232e)}';
  document.head.appendChild(st);
  var box=document.createElement('div'); box.className='g3term'; box.id='g3term';
  box.innerHTML='<div class="r"><span class="t">工期（'+'契約）</span><label>開始日<input type="date" id="g3s"></label><label>工期末<input type="date" id="g3e"></label><span class="d" id="g3d"></span><button type="button" id="g3b">保存</button></div><div class="m" id="g3m"></div><details id="g3log" hidden><summary></summary><ol></ol></details>';
  var h=[].slice.call(document.querySelectorAll('h2')).filter(function(x){return (x.textContent||'').trim()==='条件';})[0];
  var si=document.getElementById('start'), ctl=si&&si.closest('.ctl, form, fieldset');
  if(h&&h.parentNode){h.parentNode.insertBefore(box,h.nextSibling);}
  else if(ctl&&ctl.parentNode){ctl.parentNode.insertBefore(box,ctl);}
  else{var hd=document.querySelector('header')||document.querySelector('h1'); if(hd&&hd.parentNode)hd.parentNode.insertBefore(box,hd.nextSibling); else document.body.insertBefore(box,document.body.firstChild);}
  var s=$('g3s'),e=$('g3e'),b=$('g3b'),m=$('g3m'),dd=$('g3d');
  s.value=T.start||''; e.value=T.end||'';
  function days(){ if(s.value&&e.value){var n=Math.round((Date.parse(e.value)-Date.parse(s.value))/864e5)+1; dd.textContent=n>0?'（暦日 '+n+'日）':'';} else dd.textContent=''; }
  function note(){
    var t = T.source==='saved' ? '最終更新：'+(T.updatedBy||'')+'（'+(T.updatedAt||'')+'）'
      : T.source==='default' ? '設計図書から拾った値です（まだ保存していません）。契約の工期を確かめて保存してください。'
      : '工期はまだ入力されていません。';
    if(!T.canEdit) t+=' 工期はいま保存できません（準備中）。';
    m.className='m'; m.textContent=t;
  }
  function dirty(){ b.disabled=!T.canEdit||((s.value||'')===(T.start||'')&&(e.value||'')===(T.end||'')&&T.source==='saved'); }
  if(!T.canEdit){s.disabled=true;e.disabled=true;}
  function fmt(d){ if(!d)return '未入力'; var p=d.split('-'); return p[0]+'/'+(+p[1])+'/'+(+p[2]); }
  function logs(){
    var L=T.logs||[], el=$('g3log'); if(!L.length){el.hidden=true;return;}
    el.hidden=false; el.querySelector('summary').textContent='変更の記録（'+L.length+'件）';
    var ol=el.querySelector('ol'); ol.textContent='';
    L.forEach(function(x){
      var ch=[]; if(x.oldStart!==x.newStart)ch.push('開始日 '+fmt(x.oldStart)+' → '+fmt(x.newStart)); if(x.oldEnd!==x.newEnd)ch.push('工期末 '+fmt(x.oldEnd)+' → '+fmt(x.newEnd));
      var li=document.createElement('li'); var b=document.createElement('b'); b.textContent=x.by||'（不明）';
      li.append(x.at+'　', b, '：'+(ch.join('、')||'変更なし')); ol.appendChild(li);
    });
  }
  days(); note(); dirty(); logs();
  [s,e].forEach(function(x){x.addEventListener('input',function(){days();dirty();}); x.addEventListener('change',function(){days();dirty();});});
  b.addEventListener('click',function(){
    if(s.value&&e.value&&e.value<s.value){m.className='m err'; m.textContent='工期末は開始日より後の日にしてください。'; return;}
    b.disabled=true; m.className='m'; m.textContent='保存しています…';
    fetch(T.saveUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':T.csrf},body:JSON.stringify({start:s.value||null,end:e.value||null})})
      .then(function(r){return r.json().catch(function(){return {};}).then(function(j){return {ok:r.ok,j:j,status:r.status};});})
      .then(function(r){
        if(!r.ok){m.className='m err'; m.textContent=(r.j&&r.j.message)||(r.status===419?'ログインの有効期限が切れました。ページを読み込み直してください。':'保存できませんでした。'); dirty(); return;}
        if(r.j&&r.j.changed===false){m.className='m'; m.textContent='工期は変わっていません。'; dirty(); return;}
        m.className='m ok'; m.textContent='保存しました。工程表に反映します…'; setTimeout(function(){location.reload();},500);
      })
      .catch(function(){m.className='m err'; m.textContent='通信できませんでした。もう一度お試しください。'; dirty();});
  });
})();
