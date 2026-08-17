import { Head } from '@inertiajs/react';

export default function NoAccount() {
  return (
    <>
      <Head title="Sin cuenta" />

      <div className="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center">
        <p className="font-medium">Sin cuenta</p>
        <p className="text-sm text-muted-foreground">
          No perteneces a ninguna cuenta
        </p>
      </div>
    </>
  );
}
