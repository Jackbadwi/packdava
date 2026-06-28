import React from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAppStore } from '../store/appStore';

const patientNav = [
  { path:'/',            icon:'🏠', label:'خانه'     },
  { path:'/clinical',   icon:'🧪', label:'بالینی'   },
  { path:'/daily',      icon:'📋', label:'برنامه'   },
  { path:'/risk',       icon:'📊', label:'ریسک'     },
  { path:'/progress',   icon:'📈', label:'پیشرفت'   },
];

const doctorNav = [
  { path:'/',            icon:'🏠', label:'خانه'     },
  { path:'/enter-data', icon:'🧪', label:'ورود داده' },
  { path:'/approvals',  icon:'✅', label:'تأیید'    },
  { path:'/notifications',icon:'🔔',label:'اعلانات'  },
  { path:'/settings',   icon:'⚙️', label:'تنظیمات'  },
];

export default function AppShell({ children, isDoctor, isOnline }) {
  const { unreadCount, pendingCount, syncQueues } = useAppStore();
  const navigate  = useNavigate();
  const location  = useLocation();
  const navItems  = isDoctor ? doctorNav : patientNav;

  return (
    <div style={{ display:'flex', flexDirection:'column', minHeight:'100vh', background:'#F0F4F8', fontFamily:'Vazirmatn,Tahoma', direction:'rtl' }}>

      {/* ── نوار آفلاین ── */}
      {!isOnline && (
        <div style={{
          background:'#E74C3C', color:'white', textAlign:'center',
          padding:'6px 16px', fontSize:'12px', fontWeight:'600',
          position:'sticky', top:0, zIndex:100,
        }}>
          🔴 آفلاین {pendingCount > 0 && `— ${pendingCount} رکورد در انتظار ارسال`}
        </div>
      )}

      {/* ── Header ── */}
      <header style={{
        background:'#1A7A4A', color:'white', padding:'12px 20px',
        display:'flex', alignItems:'center', justifyContent:'space-between',
        position:'sticky', top: isOnline ? 0 : 30, zIndex:99,
        boxShadow:'0 2px 8px rgba(0,0,0,.15)',
      }}>
        <div style={{ display:'flex', alignItems:'center', gap:'10px' }}>
          <span style={{ fontSize:'22px' }}>💊</span>
          <span style={{ fontSize:'17px', fontWeight:'800' }}>پک دوا</span>
        </div>
        <div style={{ display:'flex', gap:'12px', alignItems:'center' }}>
          {/* دکمه sync هنگام آنلاین + pending */}
          {isOnline && pendingCount > 0 && (
            <button
              onClick={() => syncQueues()}
              style={{
                background:'rgba(255,255,255,.2)', border:'none', color:'white',
                padding:'5px 12px', borderRadius:'99px', fontSize:'12px',
                fontWeight:'700', cursor:'pointer', fontFamily:'inherit',
                display:'flex', alignItems:'center', gap:'6px',
              }}
            >
              🔄 {pendingCount}
            </button>
          )}
          {/* اعلانات */}
          <button
            onClick={() => navigate('/notifications')}
            style={{ background:'none', border:'none', color:'white', cursor:'pointer', position:'relative', padding:'4px' }}
          >
            <span style={{ fontSize:'20px' }}>🔔</span>
            {unreadCount > 0 && (
              <span style={{
                position:'absolute', top:0, right:0,
                background:'#E74C3C', color:'white', borderRadius:'99px',
                fontSize:'9px', fontWeight:'700', padding:'1px 5px',
                minWidth:'16px', textAlign:'center',
              }}>{unreadCount}</span>
            )}
          </button>
        </div>
      </header>

      {/* ── محتوا ── */}
      <main style={{ flex:1, overflowY:'auto', paddingBottom:'70px' }}>
        {children}
      </main>

      {/* ── Bottom Navigation ── */}
      <nav style={{
        position:'fixed', bottom:0, left:0, right:0,
        background:'white', borderTop:'1px solid #E9ECEF',
        display:'flex', zIndex:99,
        paddingBottom:'env(safe-area-inset-bottom)',
        boxShadow:'0 -2px 10px rgba(0,0,0,.08)',
      }}>
        {navItems.map(item => {
          const active = location.pathname === item.path;
          return (
            <button key={item.path}
              onClick={() => navigate(item.path)}
              style={{
                flex:1, display:'flex', flexDirection:'column',
                alignItems:'center', gap:'3px', padding:'8px 4px',
                border:'none', background:'none', cursor:'pointer',
                color: active ? '#1A7A4A' : '#6C757D',
                borderTop: active ? '2px solid #1A7A4A' : '2px solid transparent',
                transition:'all .15s', fontFamily:'inherit',
              }}>
              <span style={{ fontSize:'20px' }}>{item.icon}</span>
              <span style={{ fontSize:'10px', fontWeight: active ? '700' : '500' }}>{item.label}</span>
            </button>
          );
        })}
      </nav>
    </div>
  );
}
