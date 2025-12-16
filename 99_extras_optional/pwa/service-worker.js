importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: "FIREBASE_API_KEY",
  authDomain: "yourproject.firebaseapp.com",
  projectId: "yourproject",
  messagingSenderId: "FIREBASE_SENDER_ID",
  appId: "FIREBASE_APP_ID"
});
const messaging = firebase.messaging();
