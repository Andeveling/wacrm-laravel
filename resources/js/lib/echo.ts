import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echo: Echo<'reverb'> | null = null;

export function echoClient(): Echo<'reverb'> | null {
  const key = import.meta.env.VITE_REVERB_APP_KEY;

  if (typeof key !== 'string' || key === '') {
    return null;
  }

  if (echo !== null) {
    return echo;
  }

  echo = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    Pusher,
  });

  return echo;
}
