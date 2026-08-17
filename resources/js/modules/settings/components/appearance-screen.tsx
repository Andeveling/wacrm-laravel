import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';
import AppearanceTabs from './appearance-tabs';

export default function Appearance() {
  return (
    <>
      <Head title="Appearance settings" />

      <h1 className="sr-only">Appearance settings</h1>

      <div className="space-y-6">
        <Heading
          variant="small"
          title="Appearance settings"
          description="Update the appearance settings for your account"
        />
        <AppearanceTabs />
      </div>
    </>
  );
}

Appearance.layout = {
  breadcrumbs: [
    {
      title: 'Appearance settings',
      href: editAppearance(),
    },
  ],
};
