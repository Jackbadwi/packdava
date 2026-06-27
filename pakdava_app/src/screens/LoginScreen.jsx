import React, { useState } from 'react';
import { AuthAPI } from '../services/api';

export default function LoginScreen({ onLogin }) {
  const [form, setForm]     = useState({ username:'', password:'', role:'patient' });
  const [error, setError]   = useState('');
  const [loading,setLoading]= useState(false);

  const update = k => e => setForm(f => ({ ...f, [k]: e.target.value }));

  async function submit() {
    setError(''); setLoading(true);
    try {
      const d = await AuthAPI.login(form.username, form.password, form.role);
      if (d.success) onLogin({ ...d.user, role: form.role });
      else setError(d.message || 'نام کاربری یا رمز اشتباه است');
    } catch (e) {
      setError(e.message === 'UNAUTHORIZED' ? 'احراز هویت ناموفق' : 'خطای شبکه — اتصال به سرور بررسی کنید');
    } finally {
      setLoading(false);
    }
  }

  const inp = { width:'100%', padding:'12px 14px', border:'1.5px solid #DEE2E6',
    borderRadius:'10px', fontSize:'14px', fontFamily:'Vazirmatn,Tahoma',
    marginBottom:'12px', direction:'rtl' };

  return (
    <div style={{
      minHeight:'100vh', background:'linear-gradient(160deg,#1A7A4A,#0D5C36)',
      display:'flex', flexDirection:'column', fontFamily:'Vazirmatn,Tahoma', direction:'rtl',
    }}>
      {/* Hero */}
      <div style={{ flex:1, display:'flex', flexDirection:'column', alignItems:'center',
        justifyContent:'center', color:'white', padding:'40px 24px 0' }}>
        <div style={{ fontSize:'72px', marginBottom:'12px' }}>💊</div>
        <h1 style={{ fontSize:'32px', fontWeight:'800', margin:0 }}>پک دوا</h1>
        <p style={{ fontSize:'14px', opacity:'.8', marginTop:'8px', textAlign:'center' }}>
          سامانه مدیریت یکپارچه دیابت
        </p>
      </div>

      {/* Form */}
      <div style={{
        background:'white', borderRadius:'24px 24px 0 0', padding:'32px 24px',
        marginTop:'32px', boxShadow:'0 -4px 30px rgba(0,0,0,.15)',
      }}>
        <h2 style={{ fontSize:'20px', fontWeight:'800', marginBottom:'6px', textAlign:'center' }}>ورود به سامانه</h2>
        <p style={{ fontSize:'13px', color:'#6C757D', textAlign:'center', marginBottom:'24px' }}>نقش خود را انتخاب کنید</p>

        {/* Role selector */}
        <div style={{ display:'flex', gap:'10px', marginBottom:'20px' }}>
          {[['patient','🤒','بیمار'],['doctor','👨‍⚕️','پزشک']].map(([val,icon,label]) => (
            <button key={val} onClick={() => setForm(f=>({...f,role:val}))}
              style={{
                flex:1, padding:'14px', border:`2px solid ${form.role===val?'#1A7A4A':'#DEE2E6'}`,
                borderRadius:'12px', background: form.role===val?'#E8F5EE':'white',
                color: form.role===val?'#1A7A4A':'#6C757D', cursor:'pointer',
                fontFamily:'inherit', fontSize:'14px', fontWeight:'700',
                display:'flex', flexDirection:'column', alignItems:'center', gap:'6px',
              }}>
              <span style={{ fontSize:'28px' }}>{icon}</span>{label}
            </button>
          ))}
        </div>

        <input style={inp} placeholder="نام کاربری" value={form.username} onChange={update('username')} autoComplete="username" />
        <input style={inp} placeholder="رمز عبور" type="password" value={form.password} onChange={update('password')} autoComplete="current-password" />

        {error && (
          <div style={{ background:'#FEF2F2', color:'#E74C3C', padding:'10px 14px', borderRadius:'8px', fontSize:'12px', marginBottom:'12px' }}>
            ⚠️ {error}
          </div>
        )}

        <button onClick={submit} disabled={loading || !form.username || !form.password}
          style={{
            width:'100%', padding:'14px', background: loading?'#ADB5BD':'#1A7A4A',
            color:'white', border:'none', borderRadius:'10px', fontSize:'16px',
            fontWeight:'700', cursor:'pointer', fontFamily:'inherit',
          }}>
          {loading ? '⏳ در حال ورود...' : 'ورود'}
        </button>
      </div>
    </div>
  );
}
