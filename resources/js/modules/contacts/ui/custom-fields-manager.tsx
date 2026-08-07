import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import type { CustomField } from '../contracts';
import { CustomFieldsPanel } from './custom-fields-panel';

interface CustomFieldsManagerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  fields: CustomField[];
  canManage: boolean;
  onChanged: () => void;
}

export function CustomFieldsManager({
  open,
  onOpenChange,
  fields,
  canManage,
  onChanged,
}: CustomFieldsManagerProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Campos personalizados</DialogTitle>
          <DialogDescription>
            Define los datos adicionales que puede guardar cada contacto.
          </DialogDescription>
        </DialogHeader>

        <CustomFieldsPanel
          fields={fields}
          canManage={canManage}
          onChanged={onChanged}
        />
      </DialogContent>
    </Dialog>
  );
}
