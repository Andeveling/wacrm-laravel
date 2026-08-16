import { Loader2 } from 'lucide-react';
import { useId, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import type {
  WhatsappConnection,
  WhatsappRemediationIssue,
} from '../contracts';
import { issueLabel, remediationVariant } from '../model';

export function RemediationList({
  issues,
  connections,
  busyId,
  onAssign,
  onAcknowledge,
}: {
  issues: WhatsappRemediationIssue[];
  connections: WhatsappConnection[];
  busyId: string | null;
  onAssign?: (issueId: string, connectionId: string) => void;
  onAcknowledge?: (issueId: string) => void;
}) {
  if (issues.length === 0) {
    return null;
  }

  return (
    <Card data-testid="legacy-migration-issues">
      <CardHeader>
        <CardTitle>Remediación de migración</CardTitle>
        <CardDescription>
          Estos casos no se asignaron en silencio. Elige una conexión o márcalos
          como atendidos.
        </CardDescription>
      </CardHeader>
      <CardContent className="grid gap-4">
        {issues.map((issue) =>
          remediationVariant(issue.kind) === 'assign' ? (
            <AssignRemediationIssue
              key={issue.id}
              issue={issue}
              connections={connections}
              locked={busyId !== null}
              busy={busyId === issue.id}
              onAssign={onAssign}
            />
          ) : (
            <AcknowledgeRemediationIssue
              key={issue.id}
              issue={issue}
              locked={busyId !== null}
              busy={busyId === issue.id}
              onAcknowledge={onAcknowledge}
            />
          ),
        )}
      </CardContent>
    </Card>
  );
}

function IssueSummary({ issue }: { issue: WhatsappRemediationIssue }) {
  return (
    <div className="grid gap-1">
      <p className="text-sm font-medium text-foreground">
        {issue.contact_name ?? 'Configuración heredada'}
      </p>
      <p className="text-xs text-muted-foreground">{issueLabel(issue.kind)}</p>
    </div>
  );
}

function AssignRemediationIssue({
  issue,
  connections,
  locked,
  busy,
  onAssign,
}: {
  issue: WhatsappRemediationIssue;
  connections: WhatsappConnection[];
  locked: boolean;
  busy: boolean;
  onAssign?: (issueId: string, connectionId: string) => void;
}) {
  const selectId = useId();
  const [connectionId, setConnectionId] = useState(connections[0]?.id ?? '');

  return (
    <div
      className="grid gap-3 rounded-lg border p-3"
      data-testid={`legacy-issue-${issue.id}`}
    >
      <IssueSummary issue={issue} />
      {onAssign ? (
        <div className="flex flex-wrap items-end gap-2">
          <div className="grid min-w-48 flex-1 gap-1">
            <Label htmlFor={selectId}>Conexión</Label>
            <Select
              value={connectionId}
              onValueChange={setConnectionId}
              disabled={locked || connections.length === 0}
            >
              <SelectTrigger
                id={selectId}
                data-testid={`legacy-issue-connection-${issue.id}`}
                className="w-full"
              >
                <SelectValue placeholder="Selecciona una conexión" />
              </SelectTrigger>
              <SelectContent>
                {connections.map((connection) => (
                  <SelectItem key={connection.id} value={connection.id}>
                    {connection.phone_number_id ?? connection.id}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <Button
            type="button"
            size="sm"
            disabled={locked || !connectionId}
            onClick={() => onAssign(issue.id, connectionId)}
            data-testid={`legacy-issue-assign-${issue.id}`}
          >
            {busy ? <Loader2 className="size-4 animate-spin" /> : null}
            Asignar
          </Button>
        </div>
      ) : null}
    </div>
  );
}

function AcknowledgeRemediationIssue({
  issue,
  locked,
  busy,
  onAcknowledge,
}: {
  issue: WhatsappRemediationIssue;
  locked: boolean;
  busy: boolean;
  onAcknowledge?: (issueId: string) => void;
}) {
  return (
    <div
      className="grid gap-3 rounded-lg border p-3"
      data-testid={`legacy-issue-${issue.id}`}
    >
      <IssueSummary issue={issue} />
      {onAcknowledge ? (
        <div className="flex justify-end">
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={locked}
            onClick={() => onAcknowledge(issue.id)}
            data-testid={`legacy-issue-dismiss-${issue.id}`}
          >
            {busy ? <Loader2 className="size-4 animate-spin" /> : null}
            Marcar como atendido
          </Button>
        </div>
      ) : null}
    </div>
  );
}
