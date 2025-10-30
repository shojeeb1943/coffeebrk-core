(function(){
  if(typeof coffeebrkAuthConfig==='undefined'){console.error('Missing config');return;}
  function loadScript(u){return new Promise((res,rej)=>{var s=document.createElement('script');s.src=u;s.onload=res;s.onerror=rej;document.head.appendChild(s);});}
  async function init(){
    await loadScript('https://cdn.jsdelivr.net/npm/@supabase/supabase-js/dist/umd/supabase.min.js');
    const c=coffeebrkAuthConfig,s=window.supabase.createClient(c.supabaseUrl,c.supabaseAnonKey);
    const el=document.getElementById('coffeebrk-auth-root');
    if(!el){return;}
    el.innerHTML=`<input id="cbemail" placeholder="Email"><input id="cbpass" placeholder="Password" type="password"><button id="cbsignin">Sign In</button><button id="cbsignup">Sign Up</button><button id="cbgoogle">Google</button><div id="cbmsg"></div>`;
    const msg=t=>{var m=document.getElementById('cbmsg'); if(m) m.innerText=t;};

    // If returning from OAuth or a session already exists, link WP session immediately
    try{
      const { data: sessionData } = await s.auth.getSession();
      if(sessionData && sessionData.session && sessionData.session.access_token){
        fetch(c.restUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({access_token:sessionData.session.access_token})})
          .then(r=>r.json()).then(j=>{ if(j.success){ msg('Signed in.'); setTimeout(()=>location.reload(),500); } });
      }
    }catch(e){ /* ignore */ }

    document.getElementById('cbsignin').onclick=async()=>{
      const e=document.getElementById('cbemail').value,p=document.getElementById('cbpass').value;msg('Signing in...');
      const{data,error}=await s.auth.signInWithPassword({email:e,password:p});
      if(error)return msg(error.message);
      fetch(c.restUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({access_token:data.session.access_token})})
        .then(r=>r.json()).then(j=>{if(j.success){msg('Welcome!');setTimeout(()=>location.reload(),700);}else msg('Error:'+j.msg);});
    };
    document.getElementById('cbsignup').onclick=async()=>{
      const e=document.getElementById('cbemail').value,p=document.getElementById('cbpass').value;msg('Signing up...');
      const{error}=await s.auth.signUp({email:e,password:p});
      msg(error?error.message:'Check your email for confirmation.');
    };
    document.getElementById('cbgoogle').onclick=async()=>{ await s.auth.signInWithOAuth({provider:'google'}); };
  }
  document.addEventListener('DOMContentLoaded',init);
})();
