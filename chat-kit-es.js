/* AK-CSS-LOADER: load the kit stylesheet with a version so a browser never pairs new JS with an old cached CSS */
(function(){var s=document.currentScript;var base=s&&s.src?s.src.replace(/chat-kit-es\.js.*$/,''):'/designs/';var l=document.createElement('link');l.rel='stylesheet';l.href=base+'chat-kit.css?v=6fd4fc2d';document.head.appendChild(l);})();
/* ASharp Agency chat kit. Config comes from window.AK (set by chat-config.js). Talks to the same
   /backend/chat-proxy.php as the live demo site; no keys or prompts live here. */
(function(){
var C=window.AK||{};var PROXY='/backend/chat-proxy.php';
var CONV=(window.crypto&&crypto.randomUUID)?crypto.randomUUID():('c'+Date.now()+Math.random().toString(36).slice(2,10));
var hist=[],opened=false,win,body,inp,send;
function greet(){var h=new Date().getHours();return h>=4&&h<12?'Buenos días':(h<18&&h>=12?'Buenas tardes':'Buenas noches');}
function bubble(who,t){var e=document.createElement('div');e.className='b '+who;e.textContent=t;body.appendChild(e);body.scrollTop=body.scrollHeight;return e;}
function ensure(){if(!opened){opened=true;bubble('them',greet()+', gracias por comunicarse con '+C.name+" — estoy aquí las 24 horas. "+(C.ask||"¿Qué está pasando hoy?"));}}
function toggle(){win.classList.toggle('open');if(win.classList.contains('open')){ensure();inp.focus();}}
var ctxSvc=null,ctxSent=false;
function open(prefill,svc){win.classList.add('open');
 if(svc){ctxSvc=svc;ctxSent=false;
  if(!opened){opened=true;}
  bubble('them',greet()+". Veo que está viendo el servicio de "+svc+". ¿Quiere que hablemos de eso, o tiene otra cosa en mente?");
  var c=document.createElement('div');c.className='ak-chips2';
  var y=document.createElement('button');y.type='button';y.textContent='Sí, '+svc;
  var o=document.createElement('button');o.type='button';o.textContent='Otra cosa';
  y.onclick=function(){c.remove();inp.value=prefill||("Quisiera ayuda con "+svc+".");onSend(new Event('submit'));};
  o.onclick=function(){c.remove();ctxSvc=null;inp.focus();};
  c.appendChild(y);c.appendChild(o);body.appendChild(c);body.scrollTop=body.scrollHeight;inp.focus();return;}
 ensure();if(prefill){inp.value=prefill;}inp.focus();}
window.openChat=open;window.toggleChat=toggle;
async function onSend(ev){ev.preventDefault();var t=inp.value.trim();if(!t)return;inp.value='';body.querySelectorAll('.ak-times').forEach(function(x){x.remove();});bubble('us',t);var sendT=t;if(ctxSvc&&!ctxSent){ctxSent=true;sendT=t+"\n\n(The visitor opened this chat from the "+ctxSvc+" section of the website. The visitor is on the Spanish page: reply in Spanish.)";}hist.push({role:'user',content:sendT});send.disabled=true;
 var w=bubble('them typing','Thinking…');
 try{var r=await fetch(PROXY,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({messages:hist,conversation_id:CONV})});var d=await r.json();w.classList.remove('typing');
  if(d.reply){w.textContent=d.reply;hist.push({role:'assistant',content:d.reply});
   if(d.choices&&d.choices.length){var c=document.createElement('div');c.className='ak-chips2 ak-times';d.choices.forEach(function(x){var b=document.createElement('button');b.type='button';b.textContent=x.label;b.onclick=function(){c.remove();inp.value=x.text;onSend(new Event('submit'));};c.appendChild(b);});body.appendChild(c);body.scrollTop=body.scrollHeight;}var tg=w.getBoundingClientRect().top-body.getBoundingClientRect().top+body.scrollTop-8;body.scrollTop=Math.min(tg,body.scrollHeight-body.clientHeight);
  }else{w.textContent=(C.phone?"Lo siento — tengo problemas de conexión en este momento. Por favor llámenos directamente al "+C.phone+".":"Lo siento — tengo problemas de conexión en este momento. Por favor intente de nuevo en un minuto.");}
 }catch(e){w.classList.remove('typing');w.textContent=(C.phone?"Lo siento — tengo problemas de conexión en este momento. Por favor llámenos directamente al "+C.phone+".":"Lo siento — tengo problemas de conexión en este momento. Por favor intente de nuevo en un minuto.");}
 finally{send.disabled=false;}}

function fmt(v){return '$'+String(Math.round(v)).replace(/\B(?=(\d{3})+(?!\d))/g,',');}
function band(){
 var K=C.calc||{};var job=K.job||350;var U='https://asharpagency.com/?utm_source=demo&utm_medium=demo_site&utm_campaign=';
 var f=document.createElement('a');f.id='ak-float';f.href='#ak-band';f.innerHTML='<span>&#9889;</span> Obténgalo para su negocio';document.body.appendChild(f);
 if(document.querySelector('[data-field="service.details"]'))f.href=U+'float';
 if(document.querySelector('[data-field="service.details"]'))return;
 var cw=K.calls||40,miss=Math.round(cw*4.33*0.27),lost=Math.round(cw*4.33*0.27*0.5);
 var v=(new URLSearchParams(location.search).get('ak'))||C.top||'2';
 var t=document.createElement('div');t.className='ak-top ak-top'+v;
 t.innerHTML= v==='1'
  ? '<a class="c" href="#ak-band"><span class="n">'+fmt(cw*4.33*0.27*0.5*job)+'/mes</span><span class="l1">perdidos por llamadas sin contestar &nbsp;<u>Calcule el suyo →</u></span></a>'
  : '<a class="c" href="#ak-band"><span class="n">'+fmt(cw*4.33*0.27*0.5*job)+'<small>perdidos al mes</small></span><span class="l1"><span class="lg">Lo que le cuestan a su negocio las llamadas perdidas cada mes</span><span class="sm">Costo de las llamadas perdidas</span></span><span class="l2"><span class="lg"><b>'+cw+'</b> llamadas/sem. → <b>'+miss+'</b> perdidas → <b>'+lost+'</b> trabajos perdidos × <b>'+fmt(job)+'</b> por trabajo &nbsp;<u>Calcular →</u></span><span class="sm"><b>'+lost+'</b> trabajos × <b>'+fmt(job)+'</b> por trabajo &nbsp;<u>→</u></span></span></a>';
 t.innerHTML='<div class="w">'+t.innerHTML+'<button type="button" class="x" aria-label="Cerrar">&times;</button></div>';
 t.querySelector('.x').onclick=function(e){e.preventDefault();t.remove();};
 document.body.insertBefore(t,document.body.firstChild);
 var s=document.createElement('section');s.className='ak-band';s.id='ak-band';
 s.innerHTML='<div class="in"><div><div class="tag">Creado por ASharp Agency</div><h2>¿Le gustó lo que acaba de probar? Obténgalo para su negocio.</h2><p>Todo este sitio, la recepcionista con IA del teléfono y el chat del sitio web los creó ASharp Agency para negocios de oficios como el suyo.</p><ul><li>Contesta cada llamada y cada chat, de día y de noche, en inglés y en español</li><li>Agenda citas reales directamente en su calendario y le manda los detalles por mensaje</li><li>Identifica las emergencias y se las pasa al instante</li><li>Listo para su negocio en días, no en meses</li></ul></div>'+
 '<div class="ak-card"><div class="brand">A<span>Sharp</span> Agency</div><div class="q">¿Cuánto le cuestan las llamadas perdidas?</div><div class="amt"><span id="ak-out"></span><small>al mes en trabajos perdidos</small></div>'+
 '<div class="det" id="ak-det"></div>'+
 '<label for="ak-calls">Llamadas por semana <output id="ak-o-calls">'+cw+'</output></label><input type="range" id="ak-calls" min="5" max="200" step="5" value="'+cw+'">'+
 '<label for="ak-miss">Porcentaje que pierde <output id="ak-o-miss">27%</output></label><input type="range" id="ak-miss" min="5" max="60" step="1" value="27">'+
 '<label for="ak-job">Valor promedio por trabajo <output id="ak-o-job"></output></label><input type="range" id="ak-job" min="150" max="15000" step="1" value="'+job+'">'+
 '<label for="ak-close">Tasa de cierre en llamadas contestadas <output id="ak-o-close">50%</output></label><input type="range" id="ak-close" min="10" max="90" step="5" value="50">'+
 '<p class="note">'+(K.note||'')+' <a href="https://asharpagency.com/lead-leak-score.html?utm_source=demo&utm_medium=demo_site&utm_campaign=calc">Obtenga su Lead Leak Score completo →</a></p>'+
 '<a class="go" href="https://asharpagency.com/book-a-demo.html?utm_source=demo&utm_medium=demo_site&utm_campaign=section_book">Agende una llamada gratis de 20 minutos</a><a class="more" href="https://asharpagency.com/how-it-works.html?utm_source=demo&utm_medium=demo_site&utm_campaign=section_more">Vea cómo funciona</a></div></div>';
 var ft=document.querySelector('footer');if(ft&&ft.parentNode)ft.parentNode.insertBefore(s,ft);else{var w=document.querySelector('[data-hook="chat-widget"]');(w&&w.parentNode?w.parentNode:document.body).insertBefore(s,w||null);}
 var c=document.getElementById('ak-calls'),jb=document.getElementById('ak-job'),ms=document.getElementById('ak-miss'),cl=document.getElementById('ak-close'),sent=0;
 function run(ev){var missed=c.value*4.33*ms.value/100,lostj=missed*cl.value/100,m=lostj*jb.value;
  document.getElementById('ak-out').textContent=fmt(m);document.getElementById('ak-det').textContent='~'+Math.round(missed)+' llamadas perdidas y ~'+Math.round(lostj)+' trabajos perdidos al mes';
  document.getElementById('ak-o-calls').textContent=c.value;document.getElementById('ak-o-miss').textContent=ms.value+'%';document.getElementById('ak-o-job').textContent=fmt(jb.value);document.getElementById('ak-o-close').textContent=cl.value+'%';
  if(ev&&!sent){sent=1;try{gtag('event','demo_calc',{site:location.hostname});}catch(e){}try{fbq('trackCustom','demo_calc',{site:location.hostname});}catch(e){}}}
 [c,jb,ms,cl].forEach(function(x){x.addEventListener('input',run);});run();
}

/* People photos: on very wide screens (or a zoomed-out browser) some designs make the photo box a thin strip,
   which crops heads off. If a people-photo box is wider than 2.4:1, give it enough height to stay near 2.2:1. */
function fixPhotos(){document.querySelectorAll('[data-photo="crew"],[data-photo="crew2"]').forEach(function(e){
 if(e.tagName==='SECTION')return;e.style.minHeight='';var r=e.getBoundingClientRect();if(r.width<200||r.height<1)return;
 if(r.width/r.height>2.4){e.style.minHeight=Math.round(r.width/2.2)+'px';}});}
window.addEventListener('resize',function(){clearTimeout(window.__akFp);window.__akFp=setTimeout(fixPhotos,150);});
function build(){
 if(window.self!==window.top){document.documentElement.classList.add('ak-embed');return;}
 var host=document.querySelector('[data-hook="chat-widget"]')||document.body;
 var wrap=document.createElement('div');
 wrap.innerHTML='<button id="ak-launch" type="button" aria-label="Abrir chat"><svg width="26" height="26" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M128,24A104,104,0,0,0,36.18,176.88L24.83,210.93a16,16,0,0,0,20.24,20.24l34.05-11.35A104,104,0,1,0,128,24Zm0,192a87.87,87.87,0,0,1-44.06-11.81,8,8,0,0,0-6.54-.67L40,216,52.47,178.6a8,8,0,0,0-.66-6.54A88,88,0,1,1,128,216Z"/></svg></button>'+
 '<div id="ak-win" role="dialog" aria-label="Chat"><div class="ak-head"><span class="ak-dot"></span><span class="ak-who"></span><span class="ak-sub">Suele responder al instante</span><button class="ak-x" type="button" aria-label="Cerrar chat">✕</button></div><div id="ak-body"></div><form id="ak-form"><textarea id="ak-in" rows="1" placeholder="Escriba su mensaje..."></textarea><button id="ak-send" type="submit">Enviar</button></form><div class="ak-foot">Recepcionista con IA de ASharp Agency · <a href="https://asharpagency.com/?utm_source=demo&utm_medium=design_pilot&utm_campaign=chat" target="_blank" rel="noopener">Consiga una para su negocio</a></div></div>'+
 (C.client?'':'<div class="ak-ribbon">Sitio de demostración · '+(C.phone?'el chat y el teléfono contestan en vivo con IA':'el chat contesta en vivo con IA')+' · no es un negocio real · <a href="https://asharpagency.com/?utm_source=demo&utm_medium=design_pilot&utm_campaign=ribbon" target="_blank" rel="noopener">ASharp Agency</a></div>');   /* C.client (client sites, 2026-10-17): no demo ribbon */
 host.appendChild(wrap);
 win=document.getElementById('ak-win');body=document.getElementById('ak-body');inp=document.getElementById('ak-in');send=document.getElementById('ak-send');
 win.querySelector('.ak-who').textContent=(C.short||C.name)+' — Ayuda en vivo';
 document.getElementById('ak-launch').onclick=toggle;win.querySelector('.ak-x').onclick=toggle;
 document.getElementById('ak-form').onsubmit=onSend;
 inp.onkeydown=function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();onSend(e);}};
 document.addEventListener('click',function(e){var el=e.target.closest&&e.target.closest('[data-hook="chat"]');if(!el)return;e.preventDefault();
  var s=el.getAttribute('data-service');if(s){open((C.prompts&&C.prompts[s])||("Quisiera agendar "+s+"."),s);}else{open('');}});
 if(!C.client){document.body.style.paddingBottom='34px';band();}   /* client sites: no calculator band or "Get this" button */
 fixPhotos();setTimeout(fixPhotos,600);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',build);else build();
})();
