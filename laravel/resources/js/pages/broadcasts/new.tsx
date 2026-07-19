import { Head, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Step1ChooseTemplate } from '@/components/broadcasts/step1-choose-template';
import type { AudienceConfig } from '@/components/broadcasts/step2-select-audience';
import { Step2SelectAudience } from '@/components/broadcasts/step2-select-audience';
import { Step3Personalize } from '@/components/broadcasts/step3-personalize';
import { Step4ScheduleSend } from '@/components/broadcasts/step4-schedule-send';
import type { MessageTemplate } from '@/types';

const STEPS = [
  { key: 'template', label: 'Plantilla' },
  { key: 'audience', label: 'Audiencia' },
  { key: 'personalize', label: 'Personalizar' },
  { key: 'send', label: 'Enviar' },
] as const;

export default function NewBroadcastPage() {
  const [currentStep, setCurrentStep] = useState(0);
  const [template, setTemplate] = useState<MessageTemplate | null>(null);
  const [audience, setAudience] = useState<AudienceConfig>({ type: 'all' });
  const [variables, setVariables] = useState<Record<string, string>>({});
  const [name, setName] = useState('');

  function handleSend() {
    if (!template) return;
    toast.success('Difusión enviada.');
    router.visit('/broadcasts/bc-0');
  }

  return (
    <>
      <Head title="Nueva difusión" />

      <div className="mx-auto max-w-3xl space-y-8">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Nueva difusión</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Envía un mensaje de plantilla a varios contactos en unos pasos.
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
              selectedTemplate={template}
              onSelect={setTemplate}
              onNext={() => setCurrentStep(1)}
              onBack={() => router.visit('/broadcasts')}
            />
          )}
          {currentStep === 1 && (
            <Step2SelectAudience
              audience={audience}
              onUpdate={setAudience}
              onNext={() => setCurrentStep(2)}
              onBack={() => setCurrentStep(0)}
            />
          )}
          {currentStep === 2 && template && (
            <Step3Personalize
              template={template}
              variables={variables}
              onUpdate={setVariables}
              onNext={() => setCurrentStep(3)}
              onBack={() => setCurrentStep(1)}
            />
          )}
          {currentStep === 3 && template && (
            <Step4ScheduleSend
              name={name}
              onNameChange={setName}
              template={template}
              audience={audience}
              onSend={handleSend}
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
    { title: 'Difusiones', href: '/broadcasts' },
    { title: 'Nueva difusión' },
  ],
};
