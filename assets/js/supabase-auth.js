
(function(){
  'use strict';
  if (typeof CoffeebrkAuth === 'undefined') { console.error('CoffeebrkAuth config missing'); return; }

  let supabaseClient = null;

  async function ensureSupabase(){
    if (window.supabase && supabaseClient) return supabaseClient;
    if (!window.supabase) {
      console.error('Supabase SDK not loaded');
      return null;
    }
    supabaseClient = window.supabase.createClient(CoffeebrkAuth.supabaseUrl, CoffeebrkAuth.supabaseAnonKey);
    return supabaseClient;
  }

  async function finalizeIfSession(){
    const s = await ensureSupabase();
    if (!s) return;
    try {
      const { data } = await s.auth.getSession();
      const token = data && data.session && data.session.access_token;
      if (!token) return;
      await fetch(CoffeebrkAuth.finalizeUrl, {
        method: 'POST',
        headers: { 'Content-Type':'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ access_token: token })
      }).then(r=>r.json()).then(j=>{
        if (j && j.success) {
          window.location.assign(j.redirect || CoffeebrkAuth.redirectAfter || '/');
        }
      }).catch(()=>{});
    } catch(e) { /* ignore */ }
  }

  async function wireGoogleButton(){
    const btn = document.getElementById('coffeebrk-google-btn');
    if (!btn) return;
    const s = await ensureSupabase();
    if (!s) return;
    btn.addEventListener('click', async ()=>{
      try {
        await s.auth.signInWithOAuth({
          provider: 'google',
          options: { redirectTo: window.location.href }
        });
      } catch(e) { console.error(e); }
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    finalizeIfSession();
    wireGoogleButton();
  });
})();
