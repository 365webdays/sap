import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)

// Unregister any previously-installed service worker.
// The SW caused caching issues (stale content after deploys, Safari login
// failures) that outweighed the offline benefit. The app needs network for
// API calls regardless, so offline shell caching provided little value.
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.getRegistrations().then((registrations) => {
      registrations.forEach((reg) => reg.unregister())
    })
  })
}
