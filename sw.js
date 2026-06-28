/**
 * PakDava Service Worker v3.0
 * Offline-first PWA | Background Sync | Push Notifications
 */
const SW_VERSION   = 'pakdava-v3.0';
const CACHE_STATIC  = 'pakdava-static-v3';
const CACHE_DYNAMIC = 'pakdava-dynamic-v3';
const CACHE_API     = 'pakdava-api-v3';

const STATIC_ASSETS = [
  './index.php','./manifest.json','./assets/css/main.css',
  './assets/js/app.js','./assets/js/db.js','./assets/js/sync.js','./assets/js/notify.js',
  './pwa/offline.html',
  './patient/dashboard.php','./patient/daily_plan.php','./patient/risk_assessment.php',
  './patient/soc_assessment.php','./patient/clinical_data.php','./patient/progress.php',
  './patient/notifications.php','./patient/peer_compare.php','./patient/population_data.php',
  './doctor/dashboard.php','./doctor/enter_clinical.php','./doctor/approve_data.php',
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE_STATIC).then(c=>c.addAll(STATIC_ASSETS)).then(()=>self.skipWaiting()).catch(err=>console.warn('[SW]',err)));
});

self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>![CACHE_STATIC,CACHE_DYNAMIC,CACHE_API].includes(k)).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));
});

self.addEventListener('fetch', e => {
  const {request:req} = e;
  const url = new URL(req.url);
  if(req.method!=='GET') return;
  if(url.pathname.includes('/api/')) { e.respondWith(networkFirst(req)); return; }
  if(url.pathname.endsWith('.php')||url.pathname.endsWith('/')) { e.respondWith(staleWhileRevalidate(req)); return; }
  e.respondWith(cacheFirst(req));
});

async function cacheFirst(req){
  const c=await caches.match(req);if(c)return c;
  try{const r=await fetch(req);if(r.ok)(await caches.open(CACHE_STATIC)).put(req,r.clone());return r;}
  catch{return fallback(req);}
}
async function networkFirst(req){
  try{const r=await fetch(req);if(r.ok)(await caches.open(CACHE_API)).put(req,r.clone());return r;}
  catch{return await caches.match(req)||new Response(JSON.stringify({success:false,offline:true}),{headers:{'Content-Type':'application/json'}});}
}
async function staleWhileRevalidate(req){
  const cache=await caches.open(CACHE_DYNAMIC);
  const cached=await cache.match(req);
  const fp=fetch(req).then(r=>{if(r.ok)cache.put(req,r.clone());return r;}).catch(()=>null);
  return cached||await fp||fallback(req);
}
async function fallback(req){
  if(req.destination==='document'||req.url.endsWith('.php')){
    return await caches.match('./pwa/offline.html')||new Response('<h1 dir="rtl">آفلاین</h1>',{headers:{'Content-Type':'text/html;charset=utf-8'}});
  }
  return new Response('',{status:503});
}

self.addEventListener('sync', e => {
  const tags={'sync-clinical':'clinical_queue','sync-soc':'soc_queue','sync-daily':'daily_queue','sync-risk':'risk_queue'};
  if(tags[e.tag]) e.waitUntil(syncStore(tags[e.tag]));
  if(e.tag==='sync-all') e.waitUntil(Promise.all(Object.values(tags).map(syncStore)));
});

async function syncStore(storeName){
  try{
    const db=await openIDB();
    const all=await idbGetAll(db,storeName);
    const pending=all.filter(r=>r.synced===0);
    if(!pending.length)return;
    const res=await fetch('./api/sync.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({store:storeName,records:pending})});
    if(res.ok){
      for(const r of pending) await idbPut(db,storeName,{...r,synced:1});
      notifyAll({type:'SYNC_DONE',store:storeName,count:pending.length});
    }
  }catch(err){console.warn('[SW] sync error:',err);}
}

self.addEventListener('push', e => {
  let d={title:'پک دوا',body:'اعلان جدید',type:'general',url:'./patient/notifications.php'};
  try{if(e.data)d={...d,...e.data.json()};}catch{}
  const vibs={warning:[200,100,200,100,200],clinical:[300,150,300],default:[200]};
  e.waitUntil(self.registration.showNotification(d.title,{
    body:d.body,icon:'./assets/icons/icon-192.svg',badge:'./assets/icons/icon-72.svg',
    tag:d.type,renotify:true,vibrate:vibs[d.type]||vibs.default,
    requireInteraction:['warning','clinical'].includes(d.type),
    data:{url:d.url,type:d.type},
    actions:[{action:'open',title:'مشاهده'},{action:'dismiss',title:'رد'}]
  }));
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  if(e.action==='dismiss')return;
  const url=e.notification.data?.url||'./patient/notifications.php';
  e.waitUntil(clients.matchAll({type:'window',includeUncontrolled:true}).then(list=>{
    const ex=list.find(c=>c.url.includes('pakdava'));
    if(ex){ex.focus();return ex.navigate(url);}
    return clients.openWindow(url);
  }));
});

const IDB_NAME='PakDavaDB',IDB_VERSION=4;
const IDB_STORES=['clinical_queue','soc_queue','daily_queue','risk_queue','notifications','user_profile','clinical_history','peer_data','population_cache'];

function openIDB(){
  return new Promise((res,rej)=>{
    const r=indexedDB.open(IDB_NAME,IDB_VERSION);
    r.onupgradeneeded=e=>{
      const db=e.target.result;
      IDB_STORES.forEach(name=>{
        if(!db.objectStoreNames.contains(name)){
          const s=db.createObjectStore(name,{keyPath:'local_id',autoIncrement:true});
          s.createIndex('synced','synced',{unique:false});
          s.createIndex('timestamp','timestamp',{unique:false});
        }
      });
    };
    r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error);
  });
}
function idbGetAll(db,storeName){return new Promise((res,rej)=>{const t=db.transaction(storeName,'readonly');const r=t.objectStore(storeName).getAll();r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error);});}
function idbPut(db,storeName,data){return new Promise((res,rej)=>{const t=db.transaction(storeName,'readwrite');const r=t.objectStore(storeName).put(data);r.onsuccess=()=>res();r.onerror=()=>rej(r.error);});}
function notifyAll(msg){self.clients.matchAll({includeUncontrolled:true}).then(list=>list.forEach(c=>c.postMessage(msg)));}
console.log('[SW] PakDava v3.0 loaded');
