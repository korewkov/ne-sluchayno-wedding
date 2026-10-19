import {useEffect,useState} from 'react';
import {createRoot} from 'react-dom/client';
import Wedding from './app/components/Wedding';
import Admin from './app/components/Admin';
import {api} from './app/components/Rsvp';
import './app/globals.css';
import './app/revision.css';
function AdminAccess(){const [ready,setReady]=useState(false),[allowed,setAllowed]=useState(false),[password,setPassword]=useState(''),[busy,setBusy]=useState(false),[error,setError]=useState('');useEffect(()=>{api<{authenticated:boolean}>('/api/session','').then(v=>setAllowed(v.authenticated)).catch(e=>setError(e.message)).finally(()=>setReady(true))},[]);if(!ready)return <main className="access-message"><p>Открываем кабинет…</p></main>;if(allowed)return <Admin/>;return <main className="access-message"><p className="eyebrow">НЕ СЛУЧАЙНО</p><h1>Организаторам.</h1><form style={{maxWidth:400}} className="rsvp-form" onSubmit={async e=>{e.preventDefault();setBusy(true);setError('');try{await api('/api/session','',{method:'POST',body:JSON.stringify({password})});setPassword('');setAllowed(true)}catch(e){setError((e as Error).message)}finally{setBusy(false)}}}><label style={{display:'block',margin:'25px 0'}}>Пароль<input type="password" autoComplete="current-password" value={password} onChange={e=>setPassword(e.target.value)} required style={{display:'block',width:'100%',padding:14,marginTop:10,border:'1px solid #390b2040',background:'#f8f5ef'}}/></label><button type="submit" disabled={busy} className="solid-button">{busy?'Проверяем…':'Войти'}<span>↗</span></button><p role="alert" style={{marginTop:20}}>{error}</p><a className="text-link" href="/">К приглашению ↗</a></form></main>}
createRoot(document.getElementById('root')!).render(location.pathname.startsWith('/admin')?<AdminAccess/>:<Wedding/>);

