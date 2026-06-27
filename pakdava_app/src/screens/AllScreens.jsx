// ═══════════════════════════════════════════════════════════════════
// SOCScreen.jsx
// ═══════════════════════════════════════════════════════════════════
import React, { useState } from 'react';
import { useAppStore } from '../store/appStore';
import { DB } from '../services/db';
import { DataAPI, NetworkService } from '../services/api';

const STAGES = [
  { key:'precontemplation', label:'پیش از تأمل',   color:'#E74C3C', desc:'هنوز به تغییر فکر نمی‌کنم' },
  { key:'contemplation',    label:'تأمل',           color:'#E67E22', desc:'دارم فکر می‌کنم اما تصمیم نگرفتم' },
  { key:'preparation',      label:'آماده‌سازی',     color:'#F1C40F', desc:'قصد دارم به زودی شروع کنم' },
  { key:'action',           label:'عمل',            color:'#27AE60', desc:'دارم تغییر می‌دهم (کمتر از ۶ ماه)' },
  { key:'maintenance',      label:'نگهداری',        color:'#2980B9', desc:'تغییر را حفظ می‌کنم (بیشتر از ۶ ماه)' },
];
const BARRIERS = ['کمبود وقت','عدم انگیزه','مشکلات مالی','بیماری','حمایت ضعیف'];
const DURATIONS = ['کمتر از یک هفته','یک تا چهار هفته','یک تا شش ماه','بیشتر از شش ماه'];

export function SOCScreen() {
  const { socRecords, setSoc, updatePending, isOnline } = useAppStore();
  const current = socRecords[0]?.stage || 'contemplation';
  const [selected, setSelected] = useState(current);
  const [barrier,  setBarrier]  = useState('');
  const [duration, setDuration] = useState('');
  const [comments, setComments] = useState('');
  const [saving,   setSaving]   = useState(false);
  const [msg,      setMsg]      = useState(null);

  async function save() {
    setSaving(true);
    const data = { stage: selected, barrier, duration, comments, assessment_date: new Date().toISOString().split('T')[0] };
    await DB.enqueue('soc_queue', { data });
    if (await NetworkService.isOnline()) {
      try { await DataAPI.sync('soc_queue', [{ data }]); setMsg('success'); }
      catch { setMsg('offline'); }
    } else setMsg('offline');
    await updatePending();
    setSaving(false);
  }

  const s = STAGES.find(s => s.key === selected);
  return (
    <div style={{ padding:'16px', fontFamily:'Vazirmatn,Tahoma', direction:'rtl' }}>
      <div style={{ background:`${s.color}15`, border:`1.5px solid ${s.color}`, borderRadius:'14px', padding:'16px', marginBottom:'16px' }}>
        <div style={{ fontSize:'18px', fontWeight:'800', color:s.color }}>{s.label}</div>
        <div style={{ fontSize:'13px', color:'#6C757D', marginTop:'4px' }}>{s.desc}</div>
      </div>
      {msg && <div style={{ background: msg==='success'?'#F0FDF4':'#FFF9E6', color: msg==='success'?'#15803D':'#92400E', padding:'10px 14px', borderRadius:'8px', fontSize:'13px', marginBottom:'12px' }}>{msg==='success'?'✅ ثبت شد':'📥 آفلاین — ذخیره شد'}</div>}
      <div style={{ fontSize:'15px', fontWeight:'700', marginBottom:'12px' }}>آیا تصمیم دارید سبک زندگی را تغییر دهید؟</div>
      {STAGES.map(st => (
        <div key={st.key} onClick={() => setSelected(st.key)}
          style={{ display:'flex', alignItems:'center', gap:'12px', padding:'14px', marginBottom:'8px',
            border:`2px solid ${selected===st.key?st.color:'#E9ECEF'}`,
            background: selected===st.key?`${st.color}10`:'white', borderRadius:'10px', cursor:'pointer' }}>
          <div style={{ width:'20px', height:'20px', borderRadius:'50%', border:`2px solid ${selected===st.key?st.color:'#DEE2E6'}`, background: selected===st.key?st.color:'white', flexShrink:0, display:'flex', alignItems:'center', justifyContent:'center', color:'white', fontSize:'11px' }}>{selected===st.key?'✓':''}</div>
          <div><div style={{ fontWeight:'700', color: selected===st.key?st.color:'#495057' }}>{st.label}</div><div style={{ fontSize:'12px', color:'#ADB5BD' }}>{st.desc}</div></div>
        </div>
      ))}
      <div style={{ marginTop:'16px', display:'grid', gridTemplateColumns:'1fr 1fr', gap:'10px' }}>
        {[['مانع اصلی',BARRIERS,barrier,setBarrier],['مدت زمان',DURATIONS,duration,setDuration]].map(([l,opts,val,set])=>(
          <div key={l}><label style={{ fontSize:'11px', fontWeight:'700', color:'#6C757D', display:'block', marginBottom:'4px' }}>{l}</label>
          <select value={val} onChange={e=>set(e.target.value)} style={{ width:'100%', padding:'9px', border:'1.5px solid #DEE2E6', borderRadius:'8px', fontSize:'12px', fontFamily:'inherit' }}>
            <option value="">انتخاب</option>{opts.map(o=><option key={o} value={o}>{o}</option>)}
          </select></div>
        ))}
      </div>
      <div style={{ marginTop:'12px' }}>
        <label style={{ fontSize:'11px', fontWeight:'700', color:'#6C757D', display:'block', marginBottom:'4px' }}>یادداشت</label>
        <textarea value={comments} onChange={e=>setComments(e.target.value)} rows={3}
          style={{ width:'100%', padding:'10px', border:'1.5px solid #DEE2E6', borderRadius:'8px', fontSize:'13px', fontFamily:'inherit', resize:'vertical' }} />
      </div>
      <button onClick={save} disabled={saving} style={{ width:'100%', padding:'13px', background: saving?'#ADB5BD':'#1A7A4A', color:'white', border:'none', borderRadius:'10px', fontSize:'14px', fontWeight:'700', cursor:'pointer', fontFamily:'inherit', marginTop:'12px' }}>
        {saving?'⏳ ذخیره...':'✓ ثبت مرحله تغییر'}
      </button>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// RiskScreen.jsx
// ═══════════════════════════════════════════════════════════════════
const IRAN_PREV = {
  male:   {20:4.5,30:6.0,40:8.7,50:12.3,60:14.5,70:13.2},
  female: {20:4.8,30:6.5,40:9.1,50:13.4,60:15.9,70:14.1},
};

export function RiskScreen() {
  const { riskRecords, updatePending } = useAppStore();
  const [form, setForm] = useState({ age:'42', sex:'male', bmi:'27', waist:'96', htn:'2', gluc:'0', act:'2', family:'5', diet:'1', smoking:'1' });
  const [result, setResult] = useState(null);
  const [saving, setSaving] = useState(false);

  const upd = k => e => setForm(f=>({...f,[k]:e.target.value}));

  function calc() {
    let s = 0;
    const age = parseInt(form.age);
    const bmi = parseFloat(form.bmi);
    const waist = parseInt(form.waist);
    if (age<45) s+=0; else if(age<55) s+=2; else if(age<65) s+=3; else s+=4;
    if (bmi<25) s+=0; else if(bmi<30) s+=1; else s+=3;
    const isMale = form.sex==='male';
    const midW = isMale?94:80, highW = isMale?102:88;
    if(waist<midW) s+=0; else if(waist<=highW) s+=3; else s+=4;
    s += parseInt(form.act)+parseInt(form.htn)+parseInt(form.gluc)+parseInt(form.family)+parseInt(form.diet)+parseInt(form.smoking);
    let prob,level,color;
    if(s<7){prob=1;level='low';color='#27AE60';}
    else if(s<12){prob=4;level='slightly_elevated';color='#F1C40F';}
    else if(s<15){prob=17;level='moderate';color='#E67E22';}
    else if(s<20){prob=33;level='high';color='#E74C3C';}
    else{prob=50;level='very_high';color='#8E44AD';}
    const ageK = Math.floor(age/10)*10;
    const popP = IRAN_PREV[form.sex]?.[ageK] || 10;
    setResult({ score:s, prob, level, color, popPrev:popP, relRisk:(prob/popP).toFixed(1) });
  }

  async function saveResult() {
    if(!result) return;
    setSaving(true);
    const data = { risk_score:result.score, risk_level:result.level, risk_probability:result.prob, population_prev:result.popPrev, relative_risk:result.relRisk, factors:form, assessment_date:new Date().toISOString().split('T')[0] };
    await DB.enqueue('risk_queue',{data});
    if(await NetworkService.isOnline()) { try{await DataAPI.sync('risk_queue',[{data}]);}catch{} }
    await updatePending();
    setSaving(false);
  }

  const sel = (k,opts) => (
    <select value={form[k]} onChange={upd(k)} style={{ width:'100%', padding:'9px 10px', border:'1.5px solid #DEE2E6', borderRadius:'8px', fontSize:'13px', fontFamily:'inherit', marginBottom:'10px' }}>
      {opts.map(([v,l])=><option key={v} value={v}>{l}</option>)}
    </select>
  );

  return (
    <div style={{ padding:'16px', fontFamily:'Vazirmatn,Tahoma', direction:'rtl' }}>
      <div style={{ fontSize:'14px', fontWeight:'700', marginBottom:'14px' }}>ارزیابی ریسک دیابت (FINDRISC)</div>
      {[['age','سن (سال)','number'],['bmi','BMI (kg/m²)','number'],['waist','دور کمر (cm)','number']].map(([k,l,t])=>(
        <div key={k} style={{ marginBottom:'10px' }}>
          <label style={{ fontSize:'11px', fontWeight:'700', color:'#6C757D', display:'block', marginBottom:'3px' }}>{l}</label>
          <input type={t} value={form[k]} onChange={upd(k)} style={{ width:'100%', padding:'9px 10px', border:'1.5px solid #DEE2E6', borderRadius:'8px', fontSize:'14px', fontFamily:'inherit' }} />
        </div>
      ))}
      <label style={{ fontSize:'11px', fontWeight:'700', color:'#6C757D', display:'block', marginBottom:'3px' }}>جنسیت</label>
      {sel('sex',[['male','مرد'],['female','زن']])}
      <label style={{ fontSize:'11px', fontWeight:'700', color:'#6C757D', display:'block', marginBottom:'3px' }}>فشارخون</label>
      {sel('htn',[['0','خیر'],['2','بله']])}
      <label style={{ fontSize:'11px', fontWeight:'700', color:'#6C757D', display:'block', marginBottom:'3px' }}>سابقه قند بالا</label>
      {sel('gluc',[['0','خیر'],['5','بله']])}
      <label style={{ fontSize:'11px', fontWeight:'700', color:'#6C757D', display:'block', marginBottom:'3px' }}>فعالیت بدنی</label>
      {sel('act',[['2','کم‌تحرک'],['0','فعال']])}
      <label style={{ fontSize:'11px', fontWeight:'700', color:'#6C757D', display:'block', marginBottom:'3px' }}>سابقه خانوادگی</label>
      {sel('family',[['0','ندارم'],['3','بستگان دور'],['5','والدین/خواهر/برادر']])}
      <button onClick={calc} style={{ width:'100%', padding:'12px', background:'#1A7A4A', color:'white', border:'none', borderRadius:'10px', fontSize:'14px', fontWeight:'700', cursor:'pointer', fontFamily:'inherit', marginBottom:'14px' }}>محاسبه ریسک</button>
      {result && (
        <div style={{ background:'white', borderRadius:'14px', padding:'16px', boxShadow:'0 2px 12px rgba(0,0,0,.07)' }}>
          <div style={{ textAlign:'center', marginBottom:'14px' }}>
            <div style={{ fontSize:'48px', fontWeight:'800', color:result.color }}>{result.score}</div>
            <div style={{ fontSize:'13px', color:'#6C757D' }}>از ۲۶</div>
            <div style={{ background:result.color, color:'white', display:'inline-block', padding:'4px 16px', borderRadius:'99px', fontSize:'13px', fontWeight:'700', marginTop:'6px' }}>{result.level==='low'?'پایین':result.level==='slightly_elevated'?'کمی بالا':result.level==='moderate'?'متوسط':result.level==='high'?'بالا':'بسیار بالا'}</div>
          </div>
          <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr', gap:'8px', marginBottom:'12px' }}>
            {[['احتمال ۱۰ساله',`${result.prob}٪`,result.color],['میانگین ایران',`${result.popPrev}٪`,'#2980B9'],['برابر میانگین',`${result.relRisk}×`,'#E74C3C']].map(([l,v,c])=>(
              <div key={l} style={{ textAlign:'center', background:'#F8F9FA', borderRadius:'8px', padding:'10px 6px' }}>
                <div style={{ fontSize:'20px', fontWeight:'800', color:c }}>{v}</div>
                <div style={{ fontSize:'10px', color:'#ADB5BD', marginTop:'2px' }}>{l}</div>
              </div>
            ))}
          </div>
          <button onClick={saveResult} disabled={saving} style={{ width:'100%', padding:'11px', background: saving?'#ADB5BD':'#2980B9', color:'white', border:'none', borderRadius:'8px', fontSize:'13px', fontWeight:'700', cursor:'pointer', fontFamily:'inherit' }}>
            {saving?'⏳':'💾'} ذخیره نتیجه
          </button>
        </div>
      )}
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// DailyPlanScreen.jsx
// ═══════════════════════════════════════════════════════════════════
export function DailyPlanScreen() {
  const { dailyPlans, socRecords, updatePending } = useAppStore();
  const stage = socRecords[0]?.stage || 'contemplation';
  const today = new Date().toISOString().split('T')[0];
  const todayPlan = dailyPlans.find(p=>p.plan_date===today);
  const [done, setDone] = useState(new Set());
  const [saving,setSaving]=useState(false);

  const PLANS = {
    precontemplation:[{icon:'📊',title:'ریسک شخصی شما',content:'امتیاز ریسک شما را در بخش ریسک ببینید.'},
      {icon:'📖',title:'یک واقعیت',content:'دیابت نوع ۲ در ۵۸٪ موارد با اصلاح سبک زندگی قابل پیشگیری است.'},
      {icon:'🤔',title:'سوال روز',content:'چه چیزی شما را از تغییر باز می‌دارد؟'}],
    contemplation:[{icon:'⚖️',title:'مزایا در برابر معایب',content:'امروز یک مزیت تغییر سبک زندگی را بنویسید.'},
      {icon:'🎯',title:'هدف کوچک امروز',content:'۱۰ دقیقه پیاده‌روی بعد از ناهار.'},
      {icon:'💡',title:'انگیزه روز',content:'کوچک‌ترین تغییر بهتر از بزرگ‌ترین برنامه اجرانشده است.'}],
    preparation:[{icon:'📋',title:'برنامه وعده‌ها',content:'یک وعده با سبزیجات بیشتر داشته باشید.'},
      {icon:'🏃',title:'فعالیت بدنی',content:'۲۰ دقیقه پیاده‌روی سریع.'},
      {icon:'👥',title:'حمایت اجتماعی',content:'یک نفر را از برنامه‌تان مطلع کنید.'}],
    action:[{icon:'✅',title:'ثبت امروز',content:'دقایق پیاده‌روی و وعده‌های سالم را ثبت کنید.'},
      {icon:'🏆',title:'پیشرفت شما',content:'هر روزی که در برنامه هستید یک دستاورد است!'},
      {icon:'📈',title:'سنجش هفتگی',content:'نسبت به هفته گذشته چه تغییری احساس می‌کنید؟'}],
    maintenance:[{icon:'📉',title:'روند ریسک',content:'ریسک شما کاهش یافته — ادامه دهید!'},
      {icon:'🌟',title:'الگوی همتا',content:'پیشرفت گروه ناشناس را در بخش همتایان ببینید.'},
      {icon:'🔄',title:'مرور دوره‌ای',content:'وقت ویزیت پزشک و آزمایش‌های کنترلی نزدیک است.'}],
  };

  const tasks = PLANS[stage] || PLANS.contemplation;

  async function completePlan() {
    setSaving(true);
    const data = { plan_date:today, soc_stage:stage, activities:tasks.map(t=>t.title).join(' | '), completed:1 };
    await DB.enqueue('daily_queue',{data});
    if(await NetworkService.isOnline()){try{await DataAPI.sync('daily_queue',[{data}]);}catch{}}
    await updatePending(); setSaving(false);
  }

  return (
    <div style={{ padding:'16px', fontFamily:'Vazirmatn,Tahoma', direction:'rtl' }}>
      <div style={{ background:'linear-gradient(135deg,#1A7A4A,#0D5C36)', color:'white', borderRadius:'14px', padding:'16px', marginBottom:'16px', display:'flex', justifyContent:'space-between', alignItems:'center' }}>
        <div><div style={{ fontSize:'12px', opacity:.8 }}>برنامه امروز</div><div style={{ fontSize:'18px', fontWeight:'800' }}>{today}</div></div>
        <div style={{ background:'rgba(255,255,255,.2)', borderRadius:'8px', padding:'8px 12px', textAlign:'center' }}>
          <div style={{ fontSize:'22px', fontWeight:'800' }}>{done.size}/{tasks.length}</div>
          <div style={{ fontSize:'10px', opacity:.8 }}>تکمیل</div>
        </div>
      </div>
      {tasks.map((t,i)=>(
        <div key={i} onClick={()=>setDone(d=>{const nd=new Set(d);nd.has(i)?nd.delete(i):nd.add(i);return nd;})}
          style={{ background: done.has(i)?'#F0FDF4':'white', border:`1.5px solid ${done.has(i)?'#27AE60':'#E9ECEF'}`, borderRadius:'12px', padding:'14px', marginBottom:'10px', cursor:'pointer', display:'flex', gap:'12px', alignItems:'flex-start' }}>
          <span style={{ fontSize:'24px' }}>{t.icon}</span>
          <div style={{ flex:1 }}>
            <div style={{ fontWeight:'700', fontSize:'14px', color: done.has(i)?'#15803D':'#212529' }}>{t.title}</div>
            <div style={{ fontSize:'12px', color:'#6C757D', marginTop:'4px', lineHeight:'1.6' }}>{t.content}</div>
          </div>
          <div style={{ width:'24px', height:'24px', borderRadius:'50%', border:`2px solid ${done.has(i)?'#27AE60':'#DEE2E6'}`, background: done.has(i)?'#27AE60':'white', display:'flex', alignItems:'center', justifyContent:'center', color:'white', fontSize:'13px', flexShrink:0 }}>{done.has(i)?'✓':''}</div>
        </div>
      ))}
      {done.size===tasks.length && (
        <button onClick={completePlan} disabled={saving} style={{ width:'100%', padding:'13px', background: saving?'#ADB5BD':'#1A7A4A', color:'white', border:'none', borderRadius:'10px', fontSize:'14px', fontWeight:'700', cursor:'pointer', fontFamily:'inherit', marginTop:'4px' }}>
          {saving?'⏳ ذخیره...':'🎉 تکمیل برنامه امروز'}
        </button>
      )}
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// NotificationsScreen.jsx
// ═══════════════════════════════════════════════════════════════════
export function NotificationsScreen() {
  const { notifications, setNotifications } = useAppStore();
  const TYPE_COLORS = { warning:'#E74C3C', clinical:'#2980B9', daily:'#E67E22', medication:'#E67E22', peer:'#27AE60', doctor:'#1A5276', risk:'#8E44AD', general:'#6C757D' };

  function markRead(id) {
    const updated = notifications.map(n => n.id===id?{...n,is_read:1}:n);
    setNotifications(updated);
    DB.bulkSave('notifications', updated).catch(()=>{});
  }

  if(!notifications.length) return (
    <div style={{ display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center', minHeight:'60vh', fontFamily:'Vazirmatn,Tahoma', color:'#ADB5BD' }}>
      <div style={{ fontSize:'48px' }}>🔔</div>
      <div style={{ fontSize:'16px', fontWeight:'700', marginTop:'12px' }}>اعلانی ندارید</div>
    </div>
  );

  return (
    <div style={{ fontFamily:'Vazirmatn,Tahoma', direction:'rtl' }}>
      {notifications.map(n=>(
        <div key={n.id} onClick={()=>markRead(n.id)}
          style={{ padding:'14px 16px', borderBottom:'1px solid #F1F3F4',
            background: n.is_read?'white':'#EBF5FB',
            borderRight:`3px solid ${n.is_read?'transparent':TYPE_COLORS[n.type]||'#2980B9'}` }}>
          <div style={{ display:'flex', gap:'10px', alignItems:'flex-start' }}>
            <div style={{ width:'38px', height:'38px', borderRadius:'10px', background:`${TYPE_COLORS[n.type]||'#6C757D'}20`, display:'flex', alignItems:'center', justifyContent:'center', fontSize:'18px', flexShrink:0 }}>
              {n.type==='warning'?'⚠️':n.type==='clinical'?'🧪':n.type==='daily'?'📋':n.type==='medication'?'💊':n.type==='peer'?'👥':n.type==='doctor'?'👨‍⚕️':'📢'}
            </div>
            <div style={{ flex:1 }}>
              <div style={{ fontSize:'13px', fontWeight: n.is_read?'600':'700', color:'#212529' }}>{n.title || n.message}</div>
              {n.title && <div style={{ fontSize:'12px', color:'#6C757D', marginTop:'3px', lineHeight:'1.5' }}>{n.message}</div>}
              <div style={{ fontSize:'11px', color:'#ADB5BD', marginTop:'4px' }}>{n.created_at}</div>
            </div>
            {!n.is_read && <div style={{ width:'8px', height:'8px', borderRadius:'50%', background:'#2980B9', flexShrink:0, marginTop:'4px' }}/>}
          </div>
        </div>
      ))}
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// PeerScreen.jsx — مقایسه ناشناس
// ═══════════════════════════════════════════════════════════════════
export function PeerScreen() {
  const { peerData, riskRecords } = useAppStore();
  const myRisk = riskRecords[0]?.risk_score || 0;
  const MOCK_PEERS = [
    { peer_name:'کاربر ۱۴۷', risk_reduction:23, days:45, stage:'عمل',    rank:1 },
    { peer_name:'کاربر ۲۳۸', risk_reduction:18, days:30, stage:'آماده‌سازی', rank:2 },
    { peer_name:'شما',        risk_reduction:15, days:7,  stage:'تأمل',   rank:3, me:true },
    { peer_name:'کاربر ۳۱۵', risk_reduction:12, days:14, stage:'تأمل',   rank:4 },
  ];
  const peers = peerData.length ? peerData : MOCK_PEERS;

  return (
    <div style={{ padding:'16px', fontFamily:'Vazirmatn,Tahoma', direction:'rtl' }}>
      <div style={{ background:'linear-gradient(135deg,#1A7A4A,#0D5C36)', color:'white', borderRadius:'14px', padding:'20px', marginBottom:'16px', textAlign:'center' }}>
        <div style={{ fontSize:'13px', opacity:.8 }}>جایگاه شما</div>
        <div style={{ fontSize:'36px', fontWeight:'800' }}>رتبه ۳ از {peers.length}</div>
        <div style={{ fontSize:'12px', opacity:.8, marginTop:'4px' }}>بهتر از ۷۵٪ هم‌گروهی‌ها</div>
      </div>
      <div style={{ background:'white', borderRadius:'14px', overflow:'hidden', boxShadow:'0 2px 12px rgba(0,0,0,.07)' }}>
        <div style={{ padding:'12px 16px', fontWeight:'700', fontSize:'13px', borderBottom:'1px solid #E9ECEF' }}>جدول پیشرفت گروه ناشناس</div>
        {peers.map((p,i)=>(
          <div key={i} style={{ display:'flex', alignItems:'center', gap:'12px', padding:'13px 16px', borderBottom:'1px solid #F8F9FA', background: p.me?'#F0FDF4':'white' }}>
            <div style={{ fontSize:'18px', fontWeight:'800', color: i===0?'#F1C40F':i===1?'#ADB5BD':p.me?'#1A7A4A':'#6C757D', width:'24px', textAlign:'center' }}>{p.rank}</div>
            <div style={{ width:'36px', height:'36px', borderRadius:'50%', background: p.me?'#1A7A4A':'#E8F5EE', display:'flex', alignItems:'center', justifyContent:'center', fontSize:'16px', color: p.me?'white':'#1A7A4A', fontWeight:'700', flexShrink:0 }}>{p.me?'شما':'👤'}</div>
            <div style={{ flex:1 }}>
              <div style={{ fontSize:'13px', fontWeight:'700', color: p.me?'#1A7A4A':'#212529' }}>{p.peer_name}</div>
              <div style={{ fontSize:'11px', color:'#ADB5BD' }}>مرحله: {p.stage} | {p.days} روز</div>
            </div>
            <div style={{ textAlign:'left' }}>
              <div style={{ fontSize:'15px', fontWeight:'800', color:'#27AE60' }}>↓{p.risk_reduction}٪</div>
              <div style={{ fontSize:'10px', color:'#ADB5BD' }}>کاهش ریسک</div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// PopulationScreen.jsx — داده‌های NCD-RisC
// ═══════════════════════════════════════════════════════════════════
import { Line as LineChart } from 'react-chartjs-2';

export function PopulationScreen() {
  const { ncdData } = useAppStore();
  const diabMale   = ncdData?.diabetes_prev?.male   || {};
  const diabFemale = ncdData?.diabetes_prev?.female || {};
  const FALLBACK_YEARS = [1980,1985,1990,1995,2000,2005,2010,2014];
  const FALLBACK_M     = [5.03,5.40,5.80,6.51,7.39,8.70,10.19,11.39];
  const FALLBACK_F     = [6.02,6.35,6.76,7.52,8.48,9.87,11.51,12.86];
  const years = Object.keys(diabMale).length ? Object.keys(diabMale).map(Number) : FALLBACK_YEARS;
  const mVals = Object.keys(diabMale).length ? Object.values(diabMale).map(v=>v.value||v) : FALLBACK_M;
  const fVals = Object.keys(diabFemale).length ? Object.values(diabFemale).map(v=>v.value||v) : FALLBACK_F;

  const chartData = {
    labels: years,
    datasets: [
      { label:'مردان', data:mVals, borderColor:'#2980B9', backgroundColor:'rgba(41,128,185,.08)', fill:true, tension:0.4, pointRadius:4 },
      { label:'زنان',  data:fVals, borderColor:'#E74C3C', backgroundColor:'rgba(231,76,60,.08)',  fill:true, tension:0.4, pointRadius:4 },
    ],
  };

  const STATS = [['۱۱.۴٪','شیوع مردان ۲۰۱۴','#E67E22'],['۱۲.۸٪','شیوع زنان ۲۰۱۴','#E74C3C'],['۱۸۹.۶','کلسترول مردان','#27AE60'],['۱۲۳.۸','SBP مردان (mmHg)','#2980B9']];
  return (
    <div style={{ padding:'16px', fontFamily:'Vazirmatn,Tahoma', direction:'rtl' }}>
      <div style={{ background:'linear-gradient(135deg,#1A7A4A,#0D5C36)', color:'white', borderRadius:'14px', padding:'16px', marginBottom:'16px' }}>
        <div style={{ fontSize:'15px', fontWeight:'800', marginBottom:'4px' }}>🌍 NCD-RisC ایران</div>
        <div style={{ fontSize:'12px', opacity:.9 }}>دیابت | BMI | فشارخون | کلسترول — Lancet 2016–2019</div>
      </div>
      <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'10px', marginBottom:'16px' }}>
        {STATS.map(([v,l,c])=>(
          <div key={l} style={{ background:'white', borderRadius:'12px', padding:'14px', textAlign:'center', boxShadow:'0 2px 8px rgba(0,0,0,.07)', borderTop:`3px solid ${c}` }}>
            <div style={{ fontSize:'22px', fontWeight:'800', color:c }}>{v}</div>
            <div style={{ fontSize:'11px', color:'#ADB5BD', marginTop:'3px' }}>{l}</div>
          </div>
        ))}
      </div>
      <div style={{ background:'white', borderRadius:'14px', padding:'16px', boxShadow:'0 2px 8px rgba(0,0,0,.07)' }}>
        <div style={{ fontSize:'13px', fontWeight:'700', marginBottom:'10px' }}>روند شیوع دیابت ایران ۱۹۸۰–۲۰۱۴</div>
        <LineChart data={chartData} options={{ responsive:true, plugins:{ legend:{ labels:{ font:{ family:'Vazirmatn,Tahoma', size:11 } } } }, scales:{ y:{ title:{display:true,text:'شیوع (%)'}, ticks:{font:{size:10}} }, x:{ ticks:{font:{size:10}}, grid:{display:false} } } }} height={180} />
        <div style={{ fontSize:'10px', color:'#ADB5BD', marginTop:'8px', textAlign:'center' }}>منبع: NCD-RisC — Lancet 2016</div>
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// ProgressScreen.jsx
// ═══════════════════════════════════════════════════════════════════
export function ProgressScreen() {
  const { riskRecords, clinicalData, socRecords, dailyPlans } = useAppStore();
  const riskHist = riskRecords.slice(0,6).reverse();
  const compliance = dailyPlans.filter(p=>p.completed).length / Math.max(dailyPlans.length,1) * 100;

  const chartData = {
    labels:riskHist.map(r=>r.assessment_date?.slice(5)||''),
    datasets:[{label:'امتیاز ریسک',data:riskHist.map(r=>r.risk_score||0),borderColor:'#E74C3C',backgroundColor:'rgba(231,76,60,.08)',fill:true,tension:0.4,pointRadius:5}],
  };

  return (
    <div style={{ padding:'16px', fontFamily:'Vazirmatn,Tahoma', direction:'rtl', display:'flex', flexDirection:'column', gap:'14px' }}>
      <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'10px' }}>
        {[['📊', riskRecords[0]?.risk_score||'—', 'امتیاز ریسک', '#E67E22'],
          ['🔄', socRecords[0]?.stage||'—', 'مرحله SOC', '#1A7A4A'],
          ['✅', `${compliance.toFixed(0)}٪`, 'تمکین برنامه', '#2980B9'],
          ['📉', riskRecords.length>1?(riskRecords[0].risk_score-riskRecords[riskRecords.length-1].risk_score)||0:'—', 'تغییر ریسک', '#27AE60']
        ].map(([icon,val,label,color])=>(
          <div key={label} style={{ background:'white', borderRadius:'12px', padding:'14px', boxShadow:'0 2px 8px rgba(0,0,0,.07)', borderTop:`3px solid ${color}` }}>
            <div style={{ fontSize:'20px' }}>{icon}</div>
            <div style={{ fontSize:'22px', fontWeight:'800', color, marginTop:'6px' }}>{val}</div>
            <div style={{ fontSize:'12px', color:'#6C757D', marginTop:'2px' }}>{label}</div>
          </div>
        ))}
      </div>
      {riskHist.length>1 && (
        <div style={{ background:'white', borderRadius:'14px', padding:'16px', boxShadow:'0 2px 8px rgba(0,0,0,.07)' }}>
          <div style={{ fontSize:'13px', fontWeight:'700', marginBottom:'10px' }}>روند امتیاز ریسک</div>
          <LineChart data={chartData} options={{ responsive:true, plugins:{legend:{display:false}}, scales:{ y:{min:0,max:26,ticks:{font:{size:10}}}, x:{ticks:{font:{size:10}},grid:{display:false}} } }} height={120} />
        </div>
      )}
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// SettingsScreen.jsx
// ═══════════════════════════════════════════════════════════════════
import { Preferences } from '@capacitor/preferences';
import { AuthAPI } from '../services/api';

export function SettingsScreen() {
  const { user, setUser, syncQueues, pendingCount } = useAppStore();
  const [serverUrl, setServerUrl] = useState('');

  React.useEffect(()=>{
    Preferences.get({key:'pd_server_url'}).then(({value})=>setServerUrl(value||''));
  },[]);

  async function logout() {
    await AuthAPI.logout();
    setUser(null);
    window.location.reload();
  }

  async function changeServer() {
    const url = prompt('آدرس جدید سرور:', serverUrl);
    if(!url) return;
    await Preferences.set({key:'pd_server_url',value:url.replace(/\/$/,'')});
    window.__PAKDAVA_SERVER_URL__ = url;
    setServerUrl(url);
    window.location.reload();
  }

  const Row = ({icon,label,value,onClick}) => (
    <div onClick={onClick} style={{ display:'flex', alignItems:'center', gap:'12px', padding:'14px 16px', borderBottom:'1px solid #F1F3F4', cursor: onClick?'pointer':'default' }}>
      <span style={{ fontSize:'20px' }}>{icon}</span>
      <div style={{ flex:1 }}>
        <div style={{ fontSize:'13px', fontWeight:'600', color:'#212529' }}>{label}</div>
        {value && <div style={{ fontSize:'11px', color:'#ADB5BD', marginTop:'2px', fontFamily:'monospace' }}>{value}</div>}
      </div>
      {onClick && <span style={{ color:'#ADB5BD', fontSize:'16px' }}>←</span>}
    </div>
  );

  return (
    <div style={{ fontFamily:'Vazirmatn,Tahoma', direction:'rtl' }}>
      <div style={{ background:'#1A7A4A', color:'white', padding:'24px 16px', textAlign:'center' }}>
        <div style={{ fontSize:'48px', marginBottom:'8px' }}>👤</div>
        <div style={{ fontSize:'18px', fontWeight:'800' }}>{user?.fullname||'کاربر'}</div>
        <div style={{ fontSize:'13px', opacity:.8 }}>{user?.email||''} | {user?.role==='doctor'?'پزشک':'بیمار'}</div>
      </div>
      <div style={{ background:'white', margin:'12px', borderRadius:'14px', overflow:'hidden', boxShadow:'0 2px 8px rgba(0,0,0,.07)' }}>
        <Row icon="🌐" label="آدرس سرور PHP" value={serverUrl} onClick={changeServer} />
        <Row icon="🔄" label={`همگام‌سازی${pendingCount>0?` (${pendingCount} رکورد pending)`:''}`} onClick={()=>syncQueues().then(()=>alert('همگام‌سازی کامل شد'))} />
        <Row icon="📊" label="نسخه برنامه" value="1.0.0 — PakDava" />
        <Row icon="🔒" label="خروج از حساب" onClick={logout} />
      </div>
    </div>
  );
}

export default SOCScreen;
