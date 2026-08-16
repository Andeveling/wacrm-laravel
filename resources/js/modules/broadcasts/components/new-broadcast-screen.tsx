import { Head, router, useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { broadcasts } from '@/routes';
import {
  audienceCount as audienceCountRoute,
  store,
} from '@/routes/broadcasts';
import type {
  BroadcastConnection,
  BroadcastTag,
  MessageTemplate,
} from '../types';
import { Step1ChooseTemplate } from './step1-choose-template';
import type { AudienceConfig } from './step2-select-audience';
import { Step2SelectAudience } from './step2-select-audience';
import { Step3Personalize } from './step3-personalize';
import { Step4ScheduleSend } from './step4-schedule-send';

const STEPS = [
  { key: 'template', label: 'Plantilla' },
  { key: 'audience', label: 'Audiencia' },
  { key: 'personalize', label: 'Personalizar' },
  { key: 'send', label: 'Crear' },
] as const;

interface NewBroadcastPageProps {
  templates: MessageTemplate[];
  tags: BroadcastTag[];
  connections: BroadcastConnection[];
}

export default function NewBroadcastPage({
  templates,
  tags,
  connections = [],
}: NewBroadcastPageProps) {
  const [currentStep, setCurrentStep] = useState(0);
  const [template, setTemplate] = useState<MessageTemplate | null>(null);
  const [audience, setAudience] = useState<AudienceConfig>({ type: 'all' });
  const [audienceCount, setAudienceCount] = useState<number | null>(null);
  const audienceDebounce = useRef<ReturnType<typeof setTimeout>>(undefined);
  const form = useForm({
    name: '',
    template_id: '',
    audience_type: 'all' as 'all' | 'tags',
    tag_ids: [] as string[],
    template_variables: {} as Record<string, string>,
    scheduled_at: '',
    connection_id:
      connections.find((connection) => connection.is_default)?.id ?? '',
  });

  useEffect(() => {
    const controller = new AbortController();
    clearTimeout(audienceDebounce.current);
    setAudienceCount(null);

    audienceDebounce.current = setTimeout(async () => {
      const response = await fetch(
        audienceCountRoute.url({ query: { tag_ids: audience.tagIds ?? [] } }),
        { headers: { Accept: 'application/json' }, signal: controller.signal },
      );

      if (response.ok) {
        const data: { count: number } = await response.json();
        setAudienceCount(data.count);
      }
    }, 300);

    return () => {
      controller.abort();
      clearTimeout(audienceDebounce.current);
    };
  }, [audience.tagIds]);

  function handleCreate() {
    if (!template) return;
    form.transform((data) => ({
      ...data,
      template_id: template.id,
      audience_type: audience.type,
      tag_ids: audience.tagIds ?? [],
      scheduled_at: data.scheduled_at || null,
    }));
    form.post(store.url());
  }

  return (
    <>
      <Head title="Nueva difusión" />

      <div className="mx-auto max-w-3xl space-y-8">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Nueva difusión</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Crea una difusión de plantilla para varios contactos en unos pasos.
          </p>
        </div>

        <div className="flex items-center justify-between">
          {STEPS.map((step, index) => {
            const isActive = index === currentStep;
            const isCompleted = index < currentStep;
            return (
              <div key={step.key} className="flex flex-1 items-center">
                <div className="flex items-center gap-2">
                  <div
                    className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-medium transition-all ${
                      isCompleted
                        ? 'bg-primary text-primary-foreground'
                        : isActive
                          ? 'border-2 border-primary bg-primary/10 text-primary'
                          : 'border border-border bg-muted text-muted-foreground'
                    }`}
                  >
                    {isCompleted ? <Check className="h-4 w-4" /> : index + 1}
                  </div>
                  <span
                    className={`hidden text-sm font-medium sm:block ${isActive ? 'text-foreground' : isCompleted ? 'text-primary' : 'text-muted-foreground'}`}
                  >
                    {step.label}
                  </span>
                </div>
                {index < STEPS.length - 1 && (
                  <div
                    className={`mx-3 h-px flex-1 ${index < currentStep ? 'bg-primary' : 'bg-muted'}`}
                  />
                )}
              </div>
            );
          })}
        </div>

        <div className="min-h-[400px]">
          {currentStep === 0 && (
            <Step1ChooseTemplate
              templates={templates}
              selectedTemplate={template}
              onSelect={setTemplate}
              onNext={() => setCurrentStep(1)}
              onBack={() => router.visit(broadcasts())}
            />
          )}
          {currentStep === 1 && (
            <Step2SelectAudience
              tags={tags}
              audienceCount={audienceCount}
              audience={audience}
              onUpdate={setAudience}
              onNext={() => setCurrentStep(2)}
              onBack={() => setCurrentStep(0)}
            />
          )}
          {currentStep === 2 && template && (
            <Step3Personalize
              template={template}
              variables={form.data.template_variables}
              onUpdate={(variables) =>
                form.setData('template_variables', variables)
              }
              onNext={() => setCurrentStep(3)}
              onBack={() => setCurrentStep(1)}
            />
          )}
          {currentStep === 3 && template && (
            <Step4ScheduleSend
              name={form.data.name}
              onNameChange={(name) => form.setData('name', name)}
              template={template}
              audience={audience}
              tags={tags}
              audienceCount={audienceCount ?? 0}
              scheduledAt={form.data.scheduled_at}
              onScheduledAtChange={(scheduledAt) =>
                form.setData('scheduled_at', scheduledAt)
              }
              connections={connections}
              connectionId={form.data.connection_id}
              onConnectionChange={(connectionId) =>
                form.setData('connection_id', connectionId)
              }
              onSend={handleCreate}
              onBack={() => setCurrentStep(2)}
            />
          )}
        </div>
      </div>
    </>
  );
}

NewBroadcastPage.layout = {
  breadcrumbs: [
    { title: 'Difusiones', href: broadcasts() },
    { title: 'Nueva difusión' },
  ],
};
