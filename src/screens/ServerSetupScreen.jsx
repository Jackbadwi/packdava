import React, { useState } from 'react';
import { Preferences } from '@capacitor/preferences';

export default function ServerSetupScreen({ onSet }) {
  const [url,    setUrl]    = useState('https://myhealthcare.ir/packdava');
  const [error,  setError]  = useState('');
  const [testing,setTesting]= useState(false);

  async function testAndSave() {
    setError(''); setTesting(true);
    const clean = url.replace(/\/$/, '');
    try {
      const res = await fetch(`${clean}/api/cache_status.php`, { signal: AbortSignal.timeout(8000) });
      const d   = await res.json();
      if (d.online) {
        await Preferences.set({ key: 'pd_server_url', value: clean });
        window.__PAKDAVA_SERVER_URL__ = clean;
        onSet();
      } else {
        setError('سرور پاسخ نمی‌دهد. آدرس را بررسی کنید.');
      }
    } catch (e) {
      setError(`اتصال ناموفق: ${e.message}`);
    } finally {
      setTesting(false);
    }
  }

  return (
    <div style={{
      minHeight:'100vh', background:'linear-gradient(160deg,#1A7A4A,#0D5C36)',
      display:'flex', alignItems:'center', justifyContent:'center',
      padding:'24px', fontFamily:'Vazirmatn,Tahoma', direction:'rtl',
    }}>
      <div style={{
        background:'white', borderRadius:'20px', padding:'32px 24px',
        width:'100%', maxWidth:'360px', textAlign:'center',
        boxShadow:'0 8px 40px rgba(0,0,0,.2)',
      }}>
        <div style={{ fontSize:'56px', marginBottom:'16px' }}>💊</div>
        <h1 style={{ fontSize:'22px', fontWeight:'800', marginBottom:'6px' }}>پک دوا</h1>
        <p style={{ fontSize:'13px', color:'#6C757D', marginBottom:'28px', lineHeight:'1.6' }}>
          آدرس سرور PHP را وارد کنید.<br/>
          مثال: <span style={{ fontFamily:'monospace', fontSize:'12px' }}>https://pakdava.ir</span>
        </p>

        <input
          type="url"
          value={url}
          onChange={e => setUrl(e.target.value)}
          placeholder="https://your-server.ir/pakdava"
          style={{
            width:'100%', padding:'12px 14px', border:'1.5px solid #DEE2E6',
            borderRadius:'10px', fontSize:'14px', fontFamily:'monospace',
            marginBottom:'12px', direction:'ltr', textAlign:'left',
          }}
        />

        {error && (
          <div style={{
            background:'#FEF2F2', color:'#E74C3C', padding:'10px 14px',
            borderRadius:'8px', fontSize:'12px', marginBottom:'12px',
          }}>
            ⚠️ {error}
          </div>
        )}

        <button
          onClick={testAndSave}
          disabled={testing || !url.startsWith('http')}
          style={{
            width:'100%', padding:'13px', background:'#1A7A4A', color:'white',
            border:'none', borderRadius:'10px', fontSize:'15px', fontWeight:'700',
            cursor:'pointer', fontFamily:'inherit', opacity: testing ? .7 : 1,
          }}
        >
          {testing ? '⏳ در حال بررسی...' : '✓ اتصال و ذخیره'}
        </button>

        <p style={{ fontSize:'11px', color:'#ADB5BD', marginTop:'16px' }}>
          این آدرس فقط یک بار تنظیم می‌شود<br/>
          از تنظیمات قابل تغییر است
        </p>
      </div>
    </div>
  );
}
