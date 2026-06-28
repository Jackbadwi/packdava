import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAppStore } from '../store/appStore';
import { Line, Doughnut } from 'react-chartjs-2';
import {
  Chart as ChartJS, CategoryScale, LinearScale,
  PointElement, LineElement, ArcElement, Tooltip, Legend, Filler
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Tooltip, Legend, Filler);

const SOC_MAP = {
  precontemplation: { label:'پیش از تأمل', color:'#E74C3C', step:1 },
  contemplation:    { label:'تأمل',         color:'#E67E22', step:2 },
  preparation:      { label:'آماده‌سازی',   color:'#F1C40F', step:3 },
  action:           { label:'عمل',          color:'#27AE60', step:4 },
  maintenance:      { label:'نگهداری',      color:'#2980B9', step:5 },
};

export default function DashboardScreen() {
  const navigate = useNavigate();
  const { user, clinicalData, riskRecords, socRecords, dailyPlans, isOnline } = useAppStore();

  const latest   = clinicalData[0] || {};
  const latestR  = riskRecords[0]  || {};
  const latestS  = socRecords[0]   || {};
  const todayPlan= dailyPlans.find(p => p.plan_date === new Date().toISOString().split('T')[0]);
  const soc      = SOC_MAP[latestS.stage || 'contemplation'];

  // رسم نمودار trend ریسک
  const riskHistory = riskRecords.slice(0,6).reverse();
  const chartData = {
    labels:   riskHistory.map(r => r.assessment_date?.slice(5) || ''),
    datasets: [{
      label:           'امتیاز ریسک',
      data:            riskHistory.map(r => r.risk_score || 0),
      borderColor:     '#E74C3C',
      backgroundColor: 'rgba(231,76,60,.08)',
      fill:            true,
      tension:         0.4,
      pointRadius:     5,
    }],
  };

  const card = (icon, value, label, sub, color='#1A7A4A', onClick) => (
    <div key={label} onClick={onClick}
      style={{
        background:'white', borderRadius:'14px', padding:'16px',
        boxShadow:'0 2px 12px rgba(0,0,0,.07)', cursor: onClick?'pointer':'default',
        borderTop:`3px solid ${color}`,
      }}>
      <div style={{ fontSize:'22px', marginBottom:'8px' }}>{icon}</div>
      <div style={{ fontSize:'24px', fontWeight:'800', color }}>{value}</div>
      <div style={{ fontSize:'13px', color:'#495057', marginTop:'2px', fontWeight:'600' }}>{label}</div>
      {sub && <div style={{ fontSize:'11px', color:'#ADB5BD', marginTop:'2px' }}>{sub}</div>}
    </div>
  );

  return (
    <div style={{ padding:'16px', display:'flex', flexDirection:'column', gap:'16px' }}>

      {/* خوش‌آمد */}
      <div style={{
        background:'linear-gradient(135deg,#1A7A4A,#0D5C36)',
        borderRadius:'16px', padding:'20px', color:'white',
      }}>
        <div style={{ fontSize:'14px', opacity:.8 }}>خوش آمدید،</div>
        <div style={{ fontSize:'20px', fontWeight:'800', margin:'4px 0' }}>
          {user?.fullname || 'کاربر گرامی'}
        </div>
        <div style={{
          display:'inline-flex', alignItems:'center', gap:'6px',
          background:'rgba(255,255,255,.15)', borderRadius:'99px',
          padding:'5px 14px', fontSize:'12px', fontWeight:'600', marginTop:'8px',
        }}>
          <span>🔄</span> مرحله: {soc.label}
        </div>
      </div>

      {/* کارت‌های آماری */}
      <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'12px' }}>
        {card('📊', latestR.risk_score || '—', 'امتیاز ریسک', `از ۲۶ (${latestR.risk_level || '—'})`, latestR.risk_score >= 15 ? '#E74C3C' : '#1A7A4A', () => navigate('/risk'))}
        {card('🩸', latest.fbs ? `${latest.fbs}` : '—', 'قند ناشتا (FBS)', 'mg/dL', latest.fbs >= 126 ? '#E74C3C' : latest.fbs >= 100 ? '#E67E22' : '#27AE60', () => navigate('/clinical'))}
        {card('💉', latest.bp_systolic ? `${latest.bp_systolic}/${latest.bp_diastolic}` : '—', 'فشارخون', 'mmHg', '#2980B9', () => navigate('/clinical'))}
        {card('🧪', latest.cholesterol_total ? `${latest.cholesterol_total}` : '—', 'کلسترول تام', 'mg/dL', latest.cholesterol_total >= 240 ? '#E74C3C' : '#27AE60', () => navigate('/clinical'))}
      </div>

      {/* نمودار ریسک */}
      {riskHistory.length > 1 && (
        <div style={{ background:'white', borderRadius:'14px', padding:'16px', boxShadow:'0 2px 12px rgba(0,0,0,.07)' }}>
          <div style={{ fontSize:'14px', fontWeight:'700', marginBottom:'12px' }}>روند امتیاز ریسک</div>
          <Line data={chartData} options={{
            responsive:true,
            plugins:{ legend:{ display:false } },
            scales:{
              y:{ min:0, max:26, ticks:{ font:{ size:10 } } },
              x:{ ticks:{ font:{ size:10 } }, grid:{ display:false } },
            },
          }} height={120} />
        </div>
      )}

      {/* مرحله SOC */}
      <div style={{ background:'white', borderRadius:'14px', padding:'16px', boxShadow:'0 2px 12px rgba(0,0,0,.07)' }}
        onClick={() => navigate('/soc')}>
        <div style={{ fontSize:'14px', fontWeight:'700', marginBottom:'12px' }}>مرحله تغییر رفتاری (SOC)</div>
        <div style={{
          background:`${soc.color}15`, border:`1.5px solid ${soc.color}`,
          borderRadius:'10px', padding:'14px', marginBottom:'12px',
        }}>
          <div style={{ fontSize:'16px', fontWeight:'800', color:soc.color }}>{soc.label}</div>
          <div style={{ fontSize:'12px', color:'#6C757D', marginTop:'4px' }}>
            {latestS.assessment_date || 'ارزیابی نشده'}
          </div>
        </div>
        {/* progress bar مراحل */}
        <div style={{ display:'flex', gap:'4px' }}>
          {Object.values(SOC_MAP).map(s => (
            <div key={s.step} style={{
              flex:1, height:'6px', borderRadius:'99px',
              background: s.step <= soc.step ? s.color : '#E9ECEF',
            }} />
          ))}
        </div>
      </div>

      {/* برنامه امروز */}
      <div style={{ background:'white', borderRadius:'14px', padding:'16px', boxShadow:'0 2px 12px rgba(0,0,0,.07)' }}
        onClick={() => navigate('/daily')}>
        <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:'12px' }}>
          <div style={{ fontSize:'14px', fontWeight:'700' }}>برنامه امروز</div>
          <span style={{ fontSize:'20px' }}>{todayPlan?.completed ? '✅' : '📋'}</span>
        </div>
        {todayPlan ? (
          <div style={{ fontSize:'13px', color:'#495057', lineHeight:'1.7' }}>
            {todayPlan.activities || 'فعالیت تعریف نشده'}
          </div>
        ) : (
          <div style={{ fontSize:'13px', color:'#ADB5BD', textAlign:'center', padding:'12px 0' }}>
            برنامه‌ای برای امروز تعریف نشده
            <div style={{ marginTop:'8px', color:'#1A7A4A', fontWeight:'700' }}>+ ثبت برنامه</div>
          </div>
        )}
      </div>

    </div>
  );
}
