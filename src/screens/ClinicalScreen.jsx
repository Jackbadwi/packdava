import React, { useState } from 'react';
import { useAppStore } from '../store/appStore';
import { DB } from '../services/db';
import { DataAPI, NetworkService } from '../services/api';
import { Line } from 'react-chartjs-2';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler } from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler);

const REFS = {
  fbs:   { label:'FBS (mg/dL)',         ref:'< ۱۰۰ طبیعی | ۱۰۰–۱۲۵ پیش‌دیابت',    warn: v => v>=126?'red':v>=100?'orange':'green' },
  hba1c: { label:'HbA1c (%)',            ref:'< ۵.۷ طبیعی | ۵.۷–۶.۴ پیش‌دیابت',   warn: v => v>=6.5?'red':v>=5.7?'orange':'green' },
  bp_sys:{ label:'SبP سیستولیک (mmHg)', ref:'ایران مردان: ۱۲۳.۸',               warn: v => v>=140?'red':v>=130?'orange':'green' },
  bp_dia:{ label:'BP دیاستولیک (mmHg)', ref:'< ۸۰ طبیعی',                        warn: v => v>=90?'red':v>=80?'orange':'green' },
  chol:  { label:'کلسترول تام (mg/dL)',  ref:'ایران مردان: ۱۸۹.۶ | < ۲۰۰ مطلوب', warn: v => v>=240?'red':v>=200?'orange':'green' },
  ldl:   { label:'LDL (mg/dL)',          ref:'< ۱۰۰ مطلوب',                       warn: v => v>=160?'red':v>=130?'orange':'green' },
  hdl:   { label:'HDL (mg/dL)',          ref:'مردان > ۴۰',                         warn: v => v<40?'orange':'green' },
  tg:    { label:'تری‌گلیسرید (mg/dL)', ref:'< ۱۵۰ طبیعی',                       warn: v => v>=200?'red':v>=150?'orange':'green' },
  weight:{ label:'وزن (kg)',             ref:'میانگین ایران مردان: ۸۰',           warn: () => 'blue' },
  height:{ label:'قد (cm)',              ref:'',                                   warn: () => 'blue' },
  waist: { label:'دور کمر (cm)',         ref:'مردان < ۹۴ | زنان < ۸۰',            warn: v => v>=102?'red':v>=94?'orange':'green' },
  creat: { label:'کراتینین (mg/dL)',     ref:'مردان ۰.۷–۱.۲',                     warn: v => v>1.2?'orange':'green' },
};

const COLORS = { red:'#E74C3C', orange:'#E67E22', green:'#27AE60', blue:'#2980B9' };

export default function ClinicalScreen() {
  const { clinicalData, setClinical, updatePending, isOnline } = useAppStore();
  const [form,    setForm]    = useState({});
  const [saving,  setSaving]  = useState(false);
  const [msg,     setMsg]     = useState(null);
  const [tab,     setTab]     = useState('entry'); // 'entry' | 'history'

  const update = k => e => setForm(f => ({ ...f, [k]: e.target.value }));
  const bmi    = form.weight && form.height
    ? (parseFloat(form.weight) / ((parseFloat(form.height)/100)**2)).toFixed(1)
    : null;

  async function save() {
    setSaving(true); setMsg(null);
    const data = {
      fbs:              form.fbs    ? parseFloat(form.fbs)    : null,
      ppg:              form.ppg    ? parseFloat(form.ppg)    : null,
      hba1c:            form.hba1c  ? parseFloat(form.hba1c)  : null,
      bp_systolic:      form.bp_sys ? parseInt(form.bp_sys)   : null,
      bp_diastolic:     form.bp_dia ? parseInt(form.bp_dia)   : null,
      heart_rate:       form.hr     ? parseInt(form.hr)       : null,
      cholesterol_total:form.chol   ? parseFloat(form.chol)   : null,
      ldl:              form.ldl    ? parseFloat(form.ldl)    : null,
      hdl:              form.hdl    ? parseFloat(form.hdl)    : null,
      triglycerides:    form.tg     ? parseFloat(form.tg)     : null,
      weight:           form.weight ? parseFloat(form.weight) : null,
      height:           form.height ? parseFloat(form.height) : null,
      waist_circumference: form.waist ? parseFloat(form.waist): null,
      creatinine:       form.creat  ? parseFloat(form.creat)  : null,
      symptoms:         form.symptoms || '',
      medications:      form.meds   || '',
      notes:            form.notes  || '',
      record_date:      new Date().toISOString().split('T')[0],
    };

    // ذخیره محلی در IDB
    await DB.enqueue('clinical_queue', { data });

    if (await NetworkService.isOnline()) {
      try {
        const result = await DataAPI.sync('clinical_queue', [{ data }]);
        if (result.success) {
          setMsg({ type:'success', text:'✅ داده ثبت و به سرور ارسال شد' });
        } else {
          setMsg({ type:'warn', text:'📥 ذخیره شد — ارسال در دسترس بعدی' });
        }
      } catch {
        setMsg({ type:'warn', text:'📥 آفلاین — هنگام اتصال ارسال می‌شود' });
      }
    } else {
      setMsg({ type:'warn', text:'📥 آفلاین — داده ذخیره شد' });
    }

    await updatePending();
    setSaving(false);
    setForm({});
  }

  // نمودار تاریخچه
  const hist = clinicalData.slice(0,6).reverse();
  const makeChart = (field, color, label) => ({
    labels: hist.map(r => r.record_date?.slice(5) || ''),
    datasets: [{ label, data: hist.map(r => r[field] || null),
      borderColor: color, backgroundColor: color+'15',
      fill: true, tension: 0.4, pointRadius: 4 }],
  });

  const chartOpts = { responsive:true, plugins:{ legend:{ display:false } },
    scales:{ y:{ ticks:{ font:{ size:10 } } }, x:{ ticks:{ font:{ size:10 } }, grid:{ display:false } } } };

  const Section = ({ title }) => (
    <div style={{ fontSize:'11px', fontWeight:'700', color:'#1A7A4A', textTransform:'uppercase',
      letterSpacing:'.5px', margin:'16px 0 10px', paddingBottom:'4px',
      borderBottom:'1px solid #E9ECEF' }}>{title}</div>
  );

  const Field = ({ k, type='number', step }) => {
    const ref  = REFS[k];
    if (!ref) return null;
    const val  = parseFloat(form[k]);
    const color = (!isNaN(val) && ref.warn) ? COLORS[ref.warn(val)] : '#1A7A4A';
    return (
      <div style={{ marginBottom:'14px' }}>
        <label style={{ fontSize:'11px', fontWeight:'700', color:'#6C757D', display:'block', marginBottom:'4px' }}>
          {ref.label}
        </label>
        <input
          type={type} step={step||'.1'} value={form[k]||''}
          onChange={update(k)}
          style={{
            width:'100%', padding:'10px 12px', fontSize:'14px',
            border:`1.5px solid ${!isNaN(val)?color:'#DEE2E6'}`,
            borderRadius:'8px', fontFamily:'Vazirmatn,Tahoma',
          }}
        />
        {ref.ref && <div style={{ fontSize:'10px', color:'#ADB5BD', marginTop:'3px' }}>{ref.ref}</div>}
        {bmi && k==='weight' && <div style={{ fontSize:'11px', color:'#1A7A4A', marginTop:'3px', fontWeight:'700' }}>BMI: {bmi} kg/m²</div>}
      </div>
    );
  };

  return (
    <div style={{ fontFamily:'Vazirmatn,Tahoma', direction:'rtl' }}>
      {/* تب‌ها */}
      <div style={{ display:'flex', background:'white', borderBottom:'1px solid #E9ECEF' }}>
        {[['entry','ورود داده'],['history','تاریخچه']].map(([t,l]) => (
          <button key={t} onClick={()=>setTab(t)}
            style={{ flex:1, padding:'13px', border:'none', background:'none', cursor:'pointer',
              fontSize:'13px', fontWeight: tab===t?'700':'500', fontFamily:'inherit',
              color: tab===t?'#1A7A4A':'#6C757D',
              borderBottom: tab===t?'2px solid #1A7A4A':'2px solid transparent' }}>
            {l}
          </button>
        ))}
      </div>

      {tab === 'entry' && (
        <div style={{ padding:'16px' }}>
          {msg && (
            <div style={{ background: msg.type==='success'?'#F0FDF4':'#FFF9E6',
              color: msg.type==='success'?'#15803D':'#92400E',
              padding:'10px 14px', borderRadius:'8px', fontSize:'13px', marginBottom:'16px' }}>
              {msg.text}
            </div>
          )}

          {/* مرجع NCD-RisC */}
          <div style={{ background:'linear-gradient(135deg,#1A7A4A,#0D5C36)', color:'white',
            borderRadius:'10px', padding:'12px 14px', marginBottom:'16px', fontSize:'12px' }}>
            🌍 <strong>مرجع NCD-RisC ایران:</strong> FBS طبیعی &lt;۱۰۰ | کلسترول مردان: ۱۸۹.۶ | SBP: ۱۲۳.۸ mmHg | BMI مردان: ۲۵.۷
          </div>

          <Section title="🩸 شاخص‌های دیابت" />
          <Field k="fbs" /><Field k="hba1c" step=".1" />

          <Section title="💉 فشارخون" />
          <div style={{ display:'flex', gap:'10px' }}>
            <div style={{ flex:1 }}><Field k="bp_sys" step="1" /></div>
            <div style={{ flex:1 }}><Field k="bp_dia" step="1" /></div>
          </div>

          <Section title="🧪 کلسترول (NCD-RisC)" />
          <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'0 10px' }}>
            <Field k="chol" /><Field k="ldl" />
            <Field k="hdl"  /><Field k="tg"  />
          </div>

          <Section title="📏 آنتروپومتری (NCD-RisC BMI)" />
          <div style={{ display:'flex', gap:'10px' }}>
            <div style={{ flex:1 }}><Field k="weight" /></div>
            <div style={{ flex:1 }}><Field k="height" /></div>
          </div>
          <Field k="waist" />

          <Section title="🔬 سایر" />
          <Field k="creat" step=".01" />

          <Section title="💊 دارو و علائم" />
          {[['meds','داروها'],['symptoms','علائم'],['notes','یادداشت برای پزشک']].map(([k,l])=>(
            <div key={k} style={{ marginBottom:'12px' }}>
              <label style={{ fontSize:'11px', fontWeight:'700', color:'#6C757D', display:'block', marginBottom:'4px' }}>{l}</label>
              <input type="text" value={form[k]||''} onChange={update(k)}
                style={{ width:'100%', padding:'10px 12px', border:'1.5px solid #DEE2E6',
                  borderRadius:'8px', fontSize:'13px', fontFamily:'inherit' }} />
            </div>
          ))}

          <button onClick={save} disabled={saving}
            style={{ width:'100%', padding:'14px', background: saving?'#ADB5BD':'#1A7A4A',
              color:'white', border:'none', borderRadius:'10px', fontSize:'15px',
              fontWeight:'700', cursor:'pointer', fontFamily:'inherit', marginTop:'8px' }}>
            {saving ? '⏳ در حال ذخیره...' : `📤 ارسال برای تأیید پزشک${!isOnline?' (آفلاین)':''}`}
          </button>
        </div>
      )}

      {tab === 'history' && (
        <div style={{ padding:'16px', display:'flex', flexDirection:'column', gap:'14px' }}>
          {hist.length === 0 ? (
            <div style={{ textAlign:'center', color:'#ADB5BD', padding:'40px 0' }}>داده‌ای ثبت نشده</div>
          ) : (
            <>
              {/* نمودارها */}
              {[
                ['fbs','#2980B9','FBS (mg/dL)'],
                ['hba1c','#E67E22','HbA1c (%)'],
                ['cholesterol_total','#27AE60','کلسترول (mg/dL)'],
                ['bp_systolic','#E74C3C','فشارخون سیستولیک'],
              ].map(([field,color,label])=>(
                <div key={field} style={{ background:'white', borderRadius:'12px', padding:'14px', boxShadow:'0 2px 8px rgba(0,0,0,.07)' }}>
                  <div style={{ fontSize:'12px', fontWeight:'700', color:'#495057', marginBottom:'8px' }}>{label}</div>
                  <Line data={makeChart(field,color,label)} options={chartOpts} height={100} />
                </div>
              ))}

              {/* جدول */}
              <div style={{ background:'white', borderRadius:'12px', overflow:'hidden', boxShadow:'0 2px 8px rgba(0,0,0,.07)' }}>
                <div style={{ padding:'12px 14px', fontWeight:'700', fontSize:'13px', borderBottom:'1px solid #E9ECEF' }}>آخرین رکوردها</div>
                {clinicalData.slice(0,5).map((r,i)=>(
                  <div key={i} style={{ padding:'12px 14px', borderBottom:'1px solid #F8F9FA', fontSize:'12px' }}>
                    <div style={{ fontWeight:'700', color:'#495057', marginBottom:'4px' }}>{r.record_date}</div>
                    <div style={{ display:'flex', gap:'12px', flexWrap:'wrap', color:'#6C757D' }}>
                      {r.fbs && <span>FBS: <strong>{r.fbs}</strong></span>}
                      {r.hba1c && <span>HbA1c: <strong>{r.hba1c}%</strong></span>}
                      {r.cholesterol_total && <span>کلسترول: <strong>{r.cholesterol_total}</strong></span>}
                      {r.bp_systolic && <span>BP: <strong>{r.bp_systolic}/{r.bp_diastolic}</strong></span>}
                      <span style={{ color: r.status==='approved'?'#27AE60':r.status==='pending'?'#E67E22':'#E74C3C', fontWeight:'700' }}>
                        {r.status==='approved'?'✅':r.status==='pending'?'⏳':'❌'}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </>
          )}
        </div>
      )}
    </div>
  );
}
