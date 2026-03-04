(function(){
  'use strict';
  if (typeof CoffeebrkAuth === 'undefined') { console.error('CoffeebrkAuth config missing'); return; }

  let firebaseApp = null;
  let firebaseAuth = null;
  let isProcessing = false;

  // Dynamically load Firebase SDK
  async function loadFirebaseSDK() {
    if (firebaseApp && firebaseAuth) return true;

    try {
      // Load Firebase modules
      const appModule = await import('https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js');
      const authModule = await import('https://www.gstatic.com/firebasejs/10.12.0/firebase-auth.js');

      const firebaseConfig = {
        apiKey: CoffeebrkAuth.firebaseApiKey,
        authDomain: CoffeebrkAuth.firebaseAuthDomain,
        projectId: CoffeebrkAuth.firebaseProjectId,
        storageBucket: CoffeebrkAuth.firebaseStorageBucket,
        messagingSenderId: CoffeebrkAuth.firebaseMessagingSenderId,
        appId: CoffeebrkAuth.firebaseAppId,
        measurementId: CoffeebrkAuth.firebaseMeasurementId || undefined
      };

      firebaseApp = appModule.initializeApp(firebaseConfig);
      firebaseAuth = authModule.getAuth(firebaseApp);

      // Store auth module functions globally
      window.CoffeebrkFirebase = {
        auth: firebaseAuth,
        signInWithPopup: authModule.signInWithPopup,
        signInWithRedirect: authModule.signInWithRedirect,
        getRedirectResult: authModule.getRedirectResult,
        GoogleAuthProvider: authModule.GoogleAuthProvider
      };

      return true;
    } catch (e) {
      console.error('Failed to load Firebase SDK:', e);
      return false;
    }
  }

  // Finalize login by sending token to WordPress
  async function finalizeWithToken(token) {
    if (isProcessing) return false;
    isProcessing = true;

    try {
      const response = await fetch(CoffeebrkAuth.finalizeUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ access_token: token })
      });
      const result = await response.json();

      if (result && result.success) {
        window.location.replace(result.redirect || CoffeebrkAuth.redirectAfter || '/');
        return true;
      } else {
        console.error('Finalize failed:', result);
        isProcessing = false;
      }
    } catch (e) {
      console.error('Finalize error:', e);
      isProcessing = false;
    }
    return false;
  }

  // Check for redirect result (in case popup was blocked and we used redirect)
  async function checkRedirectResult() {
    if (!await loadFirebaseSDK()) return;

    try {
      const { auth, getRedirectResult } = window.CoffeebrkFirebase;
      const result = await getRedirectResult(auth);

      if (result && result.user) {
        const token = await result.user.getIdToken();
        if (token) {
          await finalizeWithToken(token);
        }
      }
    } catch (e) {
      // No redirect result or error - this is normal if user didn't come from redirect
      if (e.code !== 'auth/popup-closed-by-user') {
        console.log('No redirect result:', e.code || e.message);
      }
    }
  }

  // Wire up Google sign-in button
  async function wireGoogleButton() {
    const btn = document.getElementById('coffeebrk-google-btn');
    if (!btn) return;

    btn.addEventListener('click', async (e) => {
      e.preventDefault();

      if (isProcessing) return;

      // Show loading state
      const originalText = btn.innerHTML;
      btn.innerHTML = '<span class="cbk-google-icon" aria-hidden="true"></span> Signing in...';
      btn.disabled = true;

      if (!await loadFirebaseSDK()) {
        console.error('Firebase SDK not loaded');
        btn.innerHTML = originalText;
        btn.disabled = false;
        return;
      }

      const { auth, signInWithPopup, signInWithRedirect, GoogleAuthProvider } = window.CoffeebrkFirebase;

      try {
        const provider = new GoogleAuthProvider();
        provider.addScope('email');
        provider.addScope('profile');

        // Try popup first
        const result = await signInWithPopup(auth, provider);

        if (result && result.user) {
          const token = await result.user.getIdToken();
          await finalizeWithToken(token);
        }
      } catch (e) {
        console.error('Google sign-in error:', e.code, e.message);

        if (e.code === 'auth/popup-blocked' || e.code === 'auth/popup-closed-by-user') {
          // Popup was blocked or closed, try redirect method
          try {
            const provider = new GoogleAuthProvider();
            provider.addScope('email');
            provider.addScope('profile');
            await signInWithRedirect(auth, provider);
            return; // Will redirect away
          } catch (redirectError) {
            console.error('Redirect sign-in error:', redirectError);
          }
        } else if (e.code === 'auth/unauthorized-domain') {
          alert('Error: This domain is not authorized in Firebase. Please add "' + window.location.hostname + '" to your Firebase Authentication authorized domains.');
        }

        // Reset button on error
        btn.innerHTML = originalText;
        btn.disabled = false;
      }
    });
  }

  // Initialize on DOM ready
  document.addEventListener('DOMContentLoaded', function() {
    // Check for redirect result first (in case coming back from redirect auth)
    checkRedirectResult();
    // Wire up the button
    wireGoogleButton();
  });
})();
