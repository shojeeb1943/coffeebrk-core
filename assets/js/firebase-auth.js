(function(){
  'use strict';
  if (typeof CoffeebrkAuth === 'undefined') { console.error('CoffeebrkAuth config missing'); return; }

  let firebaseApp = null;
  let firebaseAuth = null;

  // Dynamically load Firebase SDK
  async function loadFirebaseSDK() {
    if (window.firebase && firebaseApp) return true;

    try {
      // Load Firebase App
      const { initializeApp } = await import('https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js');
      // Load Firebase Auth
      const { getAuth, signInWithPopup, GoogleAuthProvider, onAuthStateChanged } = await import('https://www.gstatic.com/firebasejs/10.12.0/firebase-auth.js');

      const firebaseConfig = {
        apiKey: CoffeebrkAuth.firebaseApiKey,
        authDomain: CoffeebrkAuth.firebaseAuthDomain,
        projectId: CoffeebrkAuth.firebaseProjectId,
        storageBucket: CoffeebrkAuth.firebaseStorageBucket,
        messagingSenderId: CoffeebrkAuth.firebaseMessagingSenderId,
        appId: CoffeebrkAuth.firebaseAppId,
        measurementId: CoffeebrkAuth.firebaseMeasurementId
      };

      firebaseApp = initializeApp(firebaseConfig);
      firebaseAuth = getAuth(firebaseApp);

      // Store references globally for use in other functions
      window.CoffeebrkFirebase = {
        auth: firebaseAuth,
        signInWithPopup,
        GoogleAuthProvider,
        onAuthStateChanged
      };

      return true;
    } catch (e) {
      console.error('Failed to load Firebase SDK:', e);
      return false;
    }
  }

  // Finalize login by sending token to WordPress
  async function finalizeWithToken(token) {
    try {
      const response = await fetch(CoffeebrkAuth.finalizeUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ access_token: token, provider: 'firebase' })
      });
      const result = await response.json();
      if (result && result.success) {
        window.location.replace(result.redirect || CoffeebrkAuth.redirectAfter || '/');
        return true;
      } else {
        console.error('Finalize failed:', result);
      }
    } catch (e) {
      console.error('Finalize error:', e);
    }
    return false;
  }

  // Check if user is already signed in
  async function checkExistingSession() {
    if (!await loadFirebaseSDK()) return;

    const { auth, onAuthStateChanged } = window.CoffeebrkFirebase;

    onAuthStateChanged(auth, async (user) => {
      if (user) {
        try {
          const token = await user.getIdToken();
          if (token) {
            await finalizeWithToken(token);
          }
        } catch (e) {
          console.error('Error getting token:', e);
        }
      }
    });
  }

  // Wire up Google sign-in button
  async function wireGoogleButton() {
    const btn = document.getElementById('coffeebrk-google-btn');
    if (!btn) return;

    btn.addEventListener('click', async () => {
      if (!await loadFirebaseSDK()) {
        console.error('Firebase SDK not loaded');
        return;
      }

      const { auth, signInWithPopup, GoogleAuthProvider } = window.CoffeebrkFirebase;

      try {
        const provider = new GoogleAuthProvider();
        provider.addScope('email');
        provider.addScope('profile');

        const result = await signInWithPopup(auth, provider);
        const user = result.user;

        if (user) {
          const token = await user.getIdToken();
          await finalizeWithToken(token);
        }
      } catch (e) {
        console.error('Google sign-in error:', e);
        if (e.code === 'auth/popup-closed-by-user') {
          // User closed the popup, no action needed
        } else if (e.code === 'auth/popup-blocked') {
          alert('Please allow popups for this site to sign in with Google.');
        }
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function() {
    checkExistingSession();
    wireGoogleButton();
  });
})();
