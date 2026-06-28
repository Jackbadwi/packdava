// ═══════════════════════════════════════════════════════
// doctor/DashboardScreen.jsx
// ═══════════════════════════════════════════════════════
import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useAppStore } from '../../store/appStore';

export function DoctorDashboardScreen() {
  const navigate = useNavigate();
  const { user, notifications } = useAppStore();
  const unreadNotifs = notifications.filter(n => !n.is_read).length;

  const cards = [
    { icon:'👥', label:'بیماران فعال',     value:'۱۲',  color:'#2980B9', path:null },
    { icon:'⚠️', label:'هشدارها',          value:'۳',   color:'#E74C3C', path:null },
    { icon:'✅', label:'در انتظار تأیید',  value:'۵',   color:'#E67E22', path:'/approvals' },
    { icon:'📈', label:'پیشرفت مثبت',      value:'۷۴٪', color:'#27AE60', path:null },
  ];

  return (
    <div style={{ padding:'16px', fontFamily:'Vazirmatn,Tahoma', direction:'rtl' }}>
      <div style={{ background:'linear-gradient(135deg,#1A5276,#1A2980)', color:'white', borderRadius:'14px', padding:'20px', marginBottom:'16px' }}>
        <div style={{ fontSize:'14px', opacity:.8 }}>خوش آمدید،</div>
        <div style={{ fontSize:'20px', fontWeight:'800', margin:'4px 0' }}>{user?.fullname || 'پزشک'}</div>
        <div style={{ fontSize:'12px', opacity:.8 }}>پنل پزشک — پک دوا</div>
      </div>

      <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'12px', marginBottom:'16px' }}>
        {cards.map(c => (
          <div key={c.label} onClick={() => c.path && navigate(c.path)}
            style={{ background:'white', borderRadius:'12px', padding:'16px', boxShadow:'0 2px 8px rgba(0,0,0,.07)', borderTop:`3px solid ${c.color}`, cursor: c.path?'pointer':'default' }}>
            <div style={{ fontSize:'22px' }}>{c.icon}</div>
            <div style={{ fontSize:'26px', fontWeight:'800', color:c.color, margin:'6px 0 2px' }}>{c.value}</div>
            <div style={{ fontSize:'12px', color:'#6C757D' }}>{c.label}</div>
          </div>
        ))}
      </div>

      <div style={{ background:'white', borderRadius:'14px', overflow:'hidden', boxShadow:'0 2px 8px rgba(0,0,0,.07)' }}>
        <div style={{ padding:'14px 16px', fontWeight:'700', fontSize:'14px', borderBottom:'1px solid #E9ECEF' }}>اقدامات سریع</div>
        {[
          ['🧪', 'ورود داده بالینی ۳ ماهه', '/enter-data'],
          ['✅', 'تأیید داده‌های بیماران',   '/approvals'],
          ['🔔', 'اعلانات',                  '/notifications'],
        ].map(([icon, label, path]) => (
          <div key={path} onClick={() => navigate(path)}
            style={{ display:'flex', alignItems:'center', gap:'12px', padding:'14px 16px', borderBottom:'1px solid #F8F9FA', cursor:'pointer' }}>
            <span style={{ fontSize:'20px' }}>{icon}</span>
            <span style={{ fontSize:'14px', fontWeight:'600', color:'#212529', flex:1 }}>{label}</span>
            <span style={{ color:'#ADB5BD' }}>←</span>
          </div>
        ))}
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════
// doctor/ApprovalsScreen.jsx
// ═══════════════════════════════════════════════════════
export function ApprovalsScreen() {
  const { isOnline } = useAppStore();
  const [items, setItems] = React.useState([
    { id:1, patient:'علی محمدی',    type:'FBS',   value:'۱۱۸ mg/dL', time:'امروز', note:'محدوده پیش‌دیابت' },
    { id:2, patient:'فاطمه رضایی', type:'BMI',   value:'۲۸.۴',      time:'دیروز', note:'' },
    { id:3, patient:'حسن کریمی',   type:'HbA1c', value:'۷.۲٪',      time:'دیروز', note:'⚠️ بالاتر از ۶.۵ — دیابت' },
    { id:4, patient:'مریم احمدی',  type:'BP',    value:'۱۲۸/۸۲',    time:'۲ روز', note:'' },
  ]);

  function approve(id) { setItems(prev => prev.map(i => i.id===id ? {...i, approved:true} : i)); }
  function reject(id)  { setItems(prev => prev.filter(i => i.id!==id)); }

  return (
    <div style={{ fontFamily:'Vazirmatn,Tahoma', direction:'rtl' }}>
      {!isOnline && (
        <div style={{ background:'#FFF9E6', color:'#92400E', padding:'10px 16px', fontSize:'12px', fontWeight:'600' }}>
          📥 آفلاین — تأییدها هنگام اتصال ارسال می‌شوند
        </div>
      )}
      <div style={{ padding:'14px 16px', fontWeight:'700', fontSize:'14px', borderBottom:'1px solid #E9ECEF', background:'white' }}>
        {items.filter(i=>!i.approved).length} مورد در انتظار تأیید
      </div>
      {items.map(item => (
        <div key={item.id} style={{ padding:'14px 16px', borderBottom:'1px solid #F1F3F4', background: item.approved?'#F0FDF4':'white' }}>
          <div style={{ display:'flex', justifyContent:'space-between', alignItems:'flex-start', marginBottom:'8px' }}>
            <div>
              <div style={{ fontWeight:'700', fontSize:'14px', color:'#212529' }}>{item.patient}</div>
              <div style={{ fontSize:'12px', color:'#6C757D', marginTop:'2px' }}>{item.type}: <strong>{item.value}</strong> — {item.time}</div>
              {item.note && <div style={{ fontSize:'11px', color:'#E67E22', marginTop:'2px', fontWeight:'600' }}>{item.note}</div>}
            </div>
            {item.approved && <span style={{ color:'#27AE60', fontWeight:'700', fontSize:'13px' }}>✅ تأیید شد</span>}
          </div>
          {!item.approved && (
            <div style={{ display:'flex', gap:'8px' }}>
              <button onClick={() => approve(item.id)}
                style={{ flex:1, padding:'9px', background:'#1A7A4A', color:'white', border:'none', borderRadius:'8px', fontSize:'13px', fontWeight:'700', cursor:'pointer', fontFamily:'inherit' }}>
                ✓ تأیید
              </button>
              <button onClick={() => reject(item.id)}
                style={{ padding:'9px 16px', background:'#FEF2F2', color:'#E74C3C', border:'1.5px solid #FECACA', borderRadius:'8px', fontSize:'13px', fontWeight:'700', cursor:'pointer', fontFamily:'inherit' }}>
                ✗ رد
              </button>
            </div>
          )}
        </div>
      ))}
    </div>
  );
}

// ═══════════════════════════════════════════════════════
// doctor/EnterClinicalScreen.jsx
// ═══════════════════════════════════════════════════════
import { DB } from '../../services/db';
import { DataAPI, NetworkService } from '../../services/api';

export function EnterClinicalScreen() {
  const [form, setForm]   = React.useState({ patient:'', fbs:'', hba1c:'', bp_sys:'', bp_dia:'', chol:'', ldl:'', hdl:'', tg:'', weight:'', height:'', waist:'', meds:'', notes:'', soc_stage:'' });
  const [saving,setSaving]= React.useState(false);
  const [msg, setMsg]     = React.useState(null);

  const upd = k => e => setForm(f => ({...f, [k]:e.target.value}));
  const bmi = form.weight && form.height ? (parseFloat(form.weight)/((parseFloat(form.height)/100)**2)).toFixed(1) : null;

  async function save() {
    setSaving(true); setMsg(null);
    const data = {
      fbs: form.fbs ? parseFloat(form.fbs) : null,
      hba1c: form.hba1c ? parseFloat(form.hba1c) : null,
      bp_systolic: form.bp_sys ? parseInt(form.bp_sys) : null,
      bp_diastolic: form.bp_dia ? parseInt(form.bp_dia) : null,
      cholesterol_total: form.chol ? parseFloat(form.chol) : null,
      ldl: form.ldl ? parseFloat(form.ldl) : null,
      hdl: form.hdl ? parseFloat(form.hdl) : null,
      triglycerides: form.tg ? parseFloat(form.tg) : null,
      weight: form.weight ? parseFloat(form.weight) : null,
      height: form.height ? parseFloat(form.height) : null,
      waist_circumference: form.waist ? parseFloat(form.waist) : null,
      medications: form.meds,
      notes: form.notes,
      record_date: new Date().toISOString().split('T')[0],
      approved: 1,
    };
    await DB.enqueue('clinical_queue', { data });
    if (await NetworkService.isOnline()) {
      try { await DataAPI.sync('clinical_queue', [{data}]); setMsg('success'); }
      catch { setMsg('offline'); }
    } else setMsg('offline');
    setSaving(false);
  }

  const F = ({k, label, ref, step='1'}) => (
    <div style={{marginBottom:'10px'}}>
      <label style={{fontSize:'11px',fontWeight:'700',color:'#6C757D',display:'block',marginBottom:'3px'}}>{label}</label>
      <input type="number" step={step} value={form[k]} onChange={upd(k)}
        style={{width:'100%',padding:'9px 10px',border:'1.5px solid #DEE2E6',borderRadius:'8px',fontSize:'13px',fontFamily:'inherit'}} />
      {ref && <div style={{fontSize:'10px',color:'#ADB5BD',marginTop:'2px'}}>{ref}</div>}
    </div>
  );

  return (
    <div style={{padding:'16px',fontFamily:'Vazirmatn,Tahoma',direction:'rtl'}}>
      <div style={{background:'linear-gradient(135deg,#1A5276,#1A2980)',color:'white',borderRadius:'12px',padding:'14px',marginBottom:'16px',fontSize:'12px'}}>
        🔄 چرخه بالینی ۳ ماهه — داده پزشک → تأیید خودکار → اعلان فوری به بیمار
      </div>
      {msg && <div style={{background:msg==='success'?'#F0FDF4':'#FFF9E6',color:msg==='success'?'#15803D':'#92400E',padding:'10px 14px',borderRadius:'8px',fontSize:'13px',marginBottom:'12px'}}>{msg==='success'?'✅ ثبت و اعلان ارسال شد':'📥 آفلاین — هنگام اتصال ارسال می‌شود'}</div>}

      <div style={{marginBottom:'12px'}}>
        <label style={{fontSize:'11px',fontWeight:'700',color:'#6C757D',display:'block',marginBottom:'3px'}}>شناسه بیمار</label>
        <input type="text" value={form.patient} onChange={upd('patient')} placeholder="patient001" style={{width:'100%',padding:'9px 10px',border:'1.5px solid #DEE2E6',borderRadius:'8px',fontSize:'13px',fontFamily:'inherit'}} />
      </div>

      <div style={{borderTop:'1px solid #E9ECEF',paddingTop:'12px',marginBottom:'10px',fontSize:'12px',fontWeight:'700',color:'#1A5276'}}>🩸 دیابت (NCD-RisC)</div>
      <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:'0 10px'}}>
        <F k="fbs"   label="FBS (mg/dL)"  ref="پیش‌دیابت: ۱۰۰–۱۲۵" step=".1"/>
        <F k="hba1c" label="HbA1c (%)"    ref="پیش‌دیابت: ۵.۷–۶.۴"  step=".1"/>
      </div>

      <div style={{borderTop:'1px solid #E9ECEF',paddingTop:'12px',marginBottom:'10px',fontSize:'12px',fontWeight:'700',color:'#1A5276'}}>💉 فشارخون (NCD-RisC)</div>
      <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:'0 10px'}}>
        <F k="bp_sys" label="سیستولیک"  ref="ایران: ۱۲۳.۸"/>
        <F k="bp_dia" label="دیاستولیک" ref="< ۸۰ طبیعی"/>
      </div>

      <div style={{borderTop:'1px solid #E9ECEF',paddingTop:'12px',marginBottom:'10px',fontSize:'12px',fontWeight:'700',color:'#1A5276'}}>🧪 کلسترول (NCD-RisC)</div>
      <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:'0 10px'}}>
        <F k="chol" label="کلسترول تام" ref="ایران: ۱۸۹.۶" step=".1"/>
        <F k="ldl"  label="LDL"         ref="< ۱۰۰ مطلوب" step=".1"/>
        <F k="hdl"  label="HDL"         ref="> ۴۰ مردان"   step=".1"/>
        <F k="tg"   label="TG"          ref="< ۱۵۰"        step=".1"/>
      </div>

      <div style={{borderTop:'1px solid #E9ECEF',paddingTop:'12px',marginBottom:'10px',fontSize:'12px',fontWeight:'700',color:'#1A5276'}}>📏 آنتروپومتری (NCD-RisC BMI)</div>
      <div style={{display:'grid',gridTemplateColumns:'1fr 1fr 1fr',gap:'0 8px'}}>
        <F k="weight" label="وزن (kg)" step=".1"/>
        <F k="height" label="قد (cm)"  step=".1"/>
        <div style={{marginBottom:'10px'}}>
          <label style={{fontSize:'11px',fontWeight:'700',color:'#6C757D',display:'block',marginBottom:'3px'}}>BMI</label>
          <input readOnly value={bmi ? `${bmi} kg/m²` : '—'} style={{width:'100%',padding:'9px 10px',border:'1.5px solid #E9ECEF',borderRadius:'8px',fontSize:'13px',background:'#F8F9FA',fontFamily:'inherit'}}/>
          <div style={{fontSize:'10px',color:'#ADB5BD',marginTop:'2px'}}>ایران مردان: ۲۵.۷</div>
        </div>
      </div>
      <F k="waist" label="دور کمر (cm)" ref="مردان < ۹۴ | زنان < ۸۰" step=".1"/>

      <div style={{borderTop:'1px solid #E9ECEF',paddingTop:'12px',marginBottom:'10px',fontSize:'12px',fontWeight:'700',color:'#1A5276'}}>💊 درمان</div>
      {[['meds','داروهای تجویزی'],['notes','پیام به بیمار']].map(([k,l])=>(
        <div key={k} style={{marginBottom:'10px'}}>
          <label style={{fontSize:'11px',fontWeight:'700',color:'#6C757D',display:'block',marginBottom:'3px'}}>{l}</label>
          <textarea value={form[k]} onChange={upd(k)} rows={2}
            style={{width:'100%',padding:'9px 10px',border:'1.5px solid #DEE2E6',borderRadius:'8px',fontSize:'13px',fontFamily:'inherit',resize:'vertical'}}/>
        </div>
      ))}

      <div style={{marginBottom:'12px'}}>
        <label style={{fontSize:'11px',fontWeight:'700',color:'#6C757D',display:'block',marginBottom:'3px'}}>تعیین مرحله SOC</label>
        <select value={form.soc_stage} onChange={upd('soc_stage')} style={{width:'100%',padding:'9px',border:'1.5px solid #DEE2E6',borderRadius:'8px',fontSize:'13px',fontFamily:'inherit'}}>
          <option value="">— بدون تغییر</option>
          <option value="precontemplation">پیش از تأمل</option>
          <option value="contemplation">تأمل</option>
          <option value="preparation">آماده‌سازی</option>
          <option value="action">عمل</option>
          <option value="maintenance">نگهداری</option>
        </select>
      </div>

      <button onClick={save} disabled={saving||!form.patient}
        style={{width:'100%',padding:'14px',background:saving||!form.patient?'#ADB5BD':'#1A5276',color:'white',border:'none',borderRadius:'10px',fontSize:'15px',fontWeight:'700',cursor:'pointer',fontFamily:'inherit'}}>
        {saving?'⏳ ثبت...':'✓ ثبت و ارسال نوتیفیکیشن به بیمار'}
      </button>
    </div>
  );
}
