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

  // Parse access_token from URL hash (OAuth callback)
  function getHashToken(){
    if (!window.location.hash) return null;
    const params = new URLSearchParams(window.location.hash.substring(1));
    return params.get('access_token');
  }

  // Finalize login by sending token to WordPress
  async function finalizeWithToken(token){
    try {
      const response = await fetch(CoffeebrkAuth.finalizeUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ access_token: token })
      });
      const result = await response.json();
      if (result && result.success) {
        // Clear hash and redirect
        window.location.replace(result.redirect || CoffeebrkAuth.redirectAfter || '/');
        return true;
      } else {
        console.error('Finalize failed:', result);
      }
    } catch(e) {
      console.error('Finalize error:', e);
    }
    return false;
  }

  async function finalizeIfSession(){
    // First check: access_token in URL hash (OAuth callback)
    const hashToken = getHashToken();
    if (hashToken) {
      await finalizeWithToken(hashToken);
      return;
    }

    // Fallback: check existing Supabase session
    const s = await ensureSupabase();
    if (!s) return;
    try {
      const { data } = await s.auth.getSession();
      const token = data && data.session && data.session.access_token;
      if (token) {
        await finalizeWithToken(token);
      }
    } catch(e) {
      console.error('Session check error:', e);
    }
  }

  async function wireGoogleButton(){
    const btn = document.getElementById('coffeebrk-google-btn');
    if (!btn) return;
    const s = await ensureSupabase();
    if (!s) return;
    btn.addEventListener('click', async ()=>{
      try {
        // Get clean URL without hash for redirect
        const redirectUrl = window.location.origin + window.location.pathname;
        await s.auth.signInWithOAuth({
          provider: 'google',
          options: { redirectTo: redirectUrl }
        });
      } catch(e) { console.error('Google sign-in error:', e); }
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    finalizeIfSession();
    wireGoogleButton();
  });
})();
