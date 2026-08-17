import { describe, expect, it } from 'vitest';
import { canConnectWhatsapp, isProductPage } from './whatsapp-activation';

describe('isProductPage', () => {
  it('marks sidebar product screens as the activation wall', () => {
    expect(isProductPage('inbox')).toBe(true);
    expect(isProductPage('dashboard')).toBe(true);
    expect(isProductPage('notifications')).toBe(true);
  });

  it('leaves settings and account chrome off the wall', () => {
    expect(isProductPage('settings/whatsapp')).toBe(false);
    expect(isProductPage('accounts/no-account')).toBe(false);
    expect(isProductPage('accounts/members')).toBe(false);
  });
});

describe('canConnectWhatsapp', () => {
  it('lets owner and admin connect a number', () => {
    expect(canConnectWhatsapp('owner')).toBe(true);
    expect(canConnectWhatsapp('admin')).toBe(true);
  });

  it('hides the connect cta from member and viewer', () => {
    expect(canConnectWhatsapp('member')).toBe(false);
    expect(canConnectWhatsapp('viewer')).toBe(false);
    expect(canConnectWhatsapp(undefined)).toBe(false);
  });
});
