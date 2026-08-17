import { needsAttention, STEP_LABELS, STEP_ORDER } from '../model';
import type { WhatsappReadiness } from '../types';

export function ReadinessSteps({
  readiness,
}: {
  readiness: WhatsappReadiness;
}) {
  const currentStep = STEP_ORDER.indexOf(readiness);
  const attention = needsAttention(readiness);

  return (
    <div className="grid gap-2 sm:grid-cols-4">
      {STEP_ORDER.map((step, index) => {
        const completed = currentStep >= index && !attention;

        return (
          <div key={step} className="grid gap-1">
            <div
              className={`h-1 rounded-full ${completed ? 'bg-primary' : 'bg-muted'}`}
            />
            <span
              className={`text-[11px] ${completed ? 'text-foreground' : 'text-muted-foreground'}`}
            >
              {STEP_LABELS[step]}
            </span>
          </div>
        );
      })}
    </div>
  );
}
