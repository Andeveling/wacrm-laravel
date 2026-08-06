import { Head } from '@inertiajs/react';
import { Coins } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { CURRENCIES, DEFAULT_CURRENCY } from '@/lib/currency';
import { overview as settingsOverview } from '@/routes/settings';

export default function Deals() {
  const [currency, setCurrency] = useState(DEFAULT_CURRENCY);
  const [saved, setSaved] = useState(DEFAULT_CURRENCY);

  const dirty = currency !== saved;

  function handleSave() {
    setSaved(currency);
    toast.success('Moneda predeterminada actualizada.');
  }

  return (
    <>
      <Head title="Pipelines" />

      <div className="space-y-6">
        <Heading
          title="Pipelines"
          description="Moneda predeterminada y configuración de negocios."
        />

        <Card className="max-w-2xl">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Coins className="size-4 text-primary" />
              Moneda predeterminada
            </CardTitle>
            <CardDescription>
              Define la moneda que usarán los nuevos negocios y los totales del
              pipeline.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-2 sm:max-w-xs">
              <Label htmlFor="default-currency">Moneda</Label>
              <select
                id="default-currency"
                value={currency}
                onChange={(e) => setCurrency(e.target.value)}
                className="h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
              >
                {CURRENCIES.map((c) => (
                  <option key={c.code} value={c.code}>
                    {c.code} — {c.label}
                  </option>
                ))}
              </select>
            </div>

            <Button onClick={handleSave} disabled={!dirty}>
              Guardar
            </Button>
          </CardContent>
        </Card>
      </div>
    </>
  );
}

Deals.layout = {
  breadcrumbs: [
    { title: 'Settings', href: settingsOverview() },
    { title: 'Pipelines' },
  ],
};
