import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useId } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { automations } from '@/routes';
import { store } from '@/routes/automations';
import type { AutomationConnection, AutomationTriggerType } from '../contracts';
import { TRIGGER_META } from '../trigger-meta';

const INBOUND_TRIGGERS: AutomationTriggerType[] = [
  'new_message_received',
  'first_inbound_message',
  'keyword_match',
  'interactive_reply',
];

export default function NewAutomationPage({
  connections,
}: {
  connections: AutomationConnection[];
}) {
  const id = useId();
  const form = useForm({
    name: '',
    trigger_type: 'new_message_received' as AutomationTriggerType,
    connection_mode: 'trigger' as 'trigger' | 'pinned',
    connection_id:
      connections.find((connection) => connection.is_default)?.id ?? '',
    is_active: false,
  });
  const canUseTriggerConnection = INBOUND_TRIGGERS.includes(
    form.data.trigger_type,
  );

  function selectTrigger(triggerType: AutomationTriggerType) {
    form.setData('trigger_type', triggerType);
    if (!INBOUND_TRIGGERS.includes(triggerType)) {
      form.setData('connection_mode', 'pinned');
    }
  }

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.post(store.url());
  }

  return (
    <>
      <Head title="Nueva automatización" />
      <div className="mx-auto max-w-2xl space-y-6">
        <Button variant="ghost" size="sm" asChild>
          <Link href={automations()}>
            <ArrowLeft className="size-4" />
            Volver a automatizaciones
          </Link>
        </Button>
        <div>
          <h1 className="text-2xl font-bold text-foreground">
            Nueva automatización
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Define cuándo se ejecuta y cuál conexión puede enviar mensajes.
          </p>
        </div>
        <form
          onSubmit={submit}
          className="space-y-6 rounded-xl border border-border bg-card p-6"
        >
          <div className="grid gap-2">
            <Label htmlFor={`${id}-name`}>Nombre</Label>
            <Input
              id={`${id}-name`}
              value={form.data.name}
              onChange={(event) => form.setData('name', event.target.value)}
              placeholder="Ej. Mensaje de bienvenida"
            />
            {!!form.errors.name && (
              <p className="text-sm text-destructive">{form.errors.name}</p>
            )}
          </div>
          <div className="grid gap-2">
            <Label htmlFor={`${id}-trigger`}>Disparador</Label>
            <select
              id={`${id}-trigger`}
              className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
              value={form.data.trigger_type}
              onChange={(event) =>
                selectTrigger(event.target.value as AutomationTriggerType)
              }
            >
              {Object.entries(TRIGGER_META).map(([value, meta]) => (
                <option key={value} value={value}>
                  {meta.label}
                </option>
              ))}
            </select>
          </div>
          <fieldset className="grid gap-3">
            <legend className="text-sm font-medium text-foreground">
              Conexión de envío
            </legend>
            {canUseTriggerConnection && (
              <label className="flex items-start gap-2 text-sm text-foreground">
                <input
                  type="radio"
                  checked={form.data.connection_mode === 'trigger'}
                  onChange={() => form.setData('connection_mode', 'trigger')}
                />
                <span>
                  <strong>Usar la conexión disparadora</strong>
                  <br />
                  <span className="text-muted-foreground">
                    Responde por el número que recibió el evento.
                  </span>
                </span>
              </label>
            )}
            <label className="flex items-start gap-2 text-sm text-foreground">
              <input
                type="radio"
                checked={form.data.connection_mode === 'pinned'}
                onChange={() => form.setData('connection_mode', 'pinned')}
              />
              <span>
                <strong>Fijar una conexión</strong>
                <br />
                <span className="text-muted-foreground">
                  Nunca cambia silenciosamente de remitente.
                </span>
              </span>
            </label>
            {form.data.connection_mode === 'pinned' && (
              <div className="grid gap-2 pl-6">
                <Label htmlFor={`${id}-connection`}>
                  Conexión WhatsApp activa
                </Label>
                <select
                  id={`${id}-connection`}
                  className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                  value={form.data.connection_id}
                  onChange={(event) =>
                    form.setData('connection_id', event.target.value)
                  }
                >
                  <option value="">Selecciona una conexión</option>
                  {connections.map((connection) => (
                    <option key={connection.id} value={connection.id}>
                      {connection.phone_number_id}
                      {connection.is_default ? ' (predeterminada)' : ''}
                    </option>
                  ))}
                </select>
                {!!form.errors.connection_id && (
                  <p className="text-sm text-destructive">
                    {form.errors.connection_id}
                  </p>
                )}
              </div>
            )}
            {!!form.errors.connection_mode && (
              <p className="text-sm text-destructive">
                {form.errors.connection_mode}
              </p>
            )}
          </fieldset>
          <label className="flex items-center gap-2 text-sm text-foreground">
            <input
              type="checkbox"
              checked={form.data.is_active}
              onChange={(event) =>
                form.setData('is_active', event.target.checked)
              }
            />
            Activar al crear
          </label>
          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" asChild>
              <Link href={automations()}>Cancelar</Link>
            </Button>
            <Button type="submit" disabled={form.processing}>
              {form.processing ? 'Creando…' : 'Crear automatización'}
            </Button>
          </div>
        </form>
      </div>
    </>
  );
}

NewAutomationPage.layout = {
  breadcrumbs: [
    { title: 'Automatizaciones', href: automations() },
    { title: 'Nueva' },
  ],
};
