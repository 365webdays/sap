import { useEffect, useState } from "react";
import { Apple, Share, Smartphone, X } from "lucide-react";
import { Button } from "@/components/ui/button";

const DISMISS_KEY = "installPromptDismissed";
const DISMISS_DAYS = 7;

type Platform = "android" | "ios" | "none";

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: "accepted" | "dismissed" }>;
}

function isStandalone(): boolean {
  return (
    window.matchMedia("(display-mode: standalone)").matches ||
    (window.navigator as unknown as { standalone?: boolean }).standalone === true
  );
}

function detectPlatform(): Platform {
  if (isStandalone()) return "none";

  const ua = window.navigator.userAgent;
  if (/iPhone|iPad|iPod/.test(ua) && /Safari/.test(ua) && !/CriOS|FxiOS/.test(ua)) {
    return "ios";
  }
  // Android and other browsers.
  return "android";
}

function wasRecentlyDismissed(): boolean {
  const ts = localStorage.getItem(DISMISS_KEY);
  if (!ts) return false;
  const daysSince = (Date.now() - parseInt(ts, 10)) / (1000 * 60 * 60 * 24);
  return daysSince < DISMISS_DAYS;
}

function dismiss() {
  localStorage.setItem(DISMISS_KEY, Date.now().toString());
}

/**
 * Install prompt for adding the app to the home screen.
 *
 * - Android: shows immediately. If beforeinstallprompt fires, offers a
 *   one-tap install button. If it doesn't, shows manual instructions
 *   (Add to Home Screen via browser menu).
 * - iPhone (Safari): shows instructions to use Share → Add to Home Screen.
 * - Already installed: renders nothing.
 * - Dismissed: hidden for 7 days (except when `force` is true).
 */
export default function InstallPrompt({ force = false }: { force?: boolean }) {
  const [platform, setPlatform] = useState<Platform>("none");
  const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null);
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const detected = detectPlatform();
    setPlatform(detected);

    if (detected === "none") return;

    // Show immediately on both platforms — don't wait for any event.
    setVisible(force || !wasRecentlyDismissed());

    // Android: listen for beforeinstallprompt to enable one-tap install.
    if (detected === "android") {
      const handler = (e: Event) => {
        e.preventDefault();
        setDeferredPrompt(e as BeforeInstallPromptEvent);
      };
      window.addEventListener("beforeinstallprompt", handler);
      return () => window.removeEventListener("beforeinstallprompt", handler);
    }
  }, [force]);

  const handleInstall = async () => {
    if (!deferredPrompt) return;
    await deferredPrompt.prompt();
    const choice = await deferredPrompt.userChoice;
    if (choice.outcome === "accepted") {
      setVisible(false);
    } else {
      dismiss();
      setVisible(false);
    }
    setDeferredPrompt(null);
  };

  const handleDismiss = () => {
    dismiss();
    setVisible(false);
  };

  if (!visible || platform === "none") return null;

  // iPhone: instructions for the manual 2-step process.
  if (platform === "ios") {
    return (
      <div className="flex items-start gap-3 rounded-lg border border-border bg-surface p-4">
        <Apple className="mt-0.5 h-5 w-5 shrink-0 text-accent" aria-hidden="true" />
        <div className="flex-1">
          <p className="text-base font-medium text-text">Add to your home screen</p>
          <p className="mt-1 text-base text-muted">
            Tap the <Share className="inline h-3.5 w-3.5 align-text-bottom" aria-hidden="true" /> Share
            icon in Safari, then tap <span className="font-medium">"Add to Home Screen"</span>.
          </p>
        </div>
        {!force && (
          <button
            onClick={handleDismiss}
            aria-label="Dismiss"
            className="shrink-0 rounded p-2 text-muted hover:text-text"
          >
            <X className="h-5 w-5" aria-hidden="true" />
          </button>
        )}
      </div>
    );
  }

  // Android with beforeinstallprompt: one-tap install button.
  if (platform === "android" && deferredPrompt) {
    return (
      <div className="flex items-center gap-3 rounded-lg border border-border bg-surface p-4">
        <Smartphone className="h-5 w-5 shrink-0 text-accent" aria-hidden="true" />
        <div className="flex-1">
          <p className="text-base font-medium text-text">Install the app</p>
          <p className="mt-0.5 text-base text-muted">Add to your home screen for quick access.</p>
        </div>
        <div className="flex items-center gap-2">
          <Button size="sm" onClick={handleInstall}>
            Install
          </Button>
          {!force && (
            <button
              onClick={handleDismiss}
              aria-label="Dismiss"
              className="shrink-0 rounded p-2 text-muted hover:text-text"
            >
              <X className="h-5 w-5" aria-hidden="true" />
            </button>
          )}
        </div>
      </div>
    );
  }

  // Android without beforeinstallprompt: manual instructions fallback.
  return (
    <div className="flex items-start gap-3 rounded-lg border border-border bg-surface p-4">
      <Smartphone className="mt-0.5 h-5 w-5 shrink-0 text-accent" aria-hidden="true" />
      <div className="flex-1">
        <p className="text-base font-medium text-text">Add to your home screen</p>
        <p className="mt-1 text-base text-muted">
          Tap the browser menu (three dots), then tap <span className="font-medium">"Add to Home screen"</span>.
        </p>
      </div>
      {!force && (
        <button
          onClick={handleDismiss}
          aria-label="Dismiss"
          className="shrink-0 rounded p-2 text-muted hover:text-text"
        >
          <X className="h-5 w-5" aria-hidden="true" />
        </button>
      )}
    </div>
  );
}
