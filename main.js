import { initializeApp } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js";
import { getAuth, GoogleAuthProvider, signInWithPopup } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js";

const firebaseConfig = {
  apiKey: "AIzaSyCqfvYqt5NsPZH6Ib93tDOmWHHG0CEXkQw",
  authDomain: "login-6ff35.firebaseapp.com",
  projectId: "login-6ff35",
  storageBucket: "login-6ff35.firebasestorage.app",
  messagingSenderId: "355096580337",
  appId: "1:355096580337:web:96cefdea88d02cf07f9950"
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
auth.languageCode = 'en';
const provider = new GoogleAuthProvider();
const googleLogin = document.getElementById("google-login-btn");

googleLogin.addEventListener("click", function() {
  signInWithPopup(auth, provider)
    .then((result) => {
      const credential = GoogleAuthProvider.credentialFromResult(result);
      const user = result.user;
      console.log(user);
      window.location.href = "../logged.html";
    })
    .catch((error) => {
      const errorCode = error.code;
      const errorMessage = error.message;
      // You might want to handle errors here, such as displaying them to the user
      console.error(errorCode, errorMessage);
    });
});