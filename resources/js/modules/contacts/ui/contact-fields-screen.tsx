import { Head, router } from '@inertiajs/react';
import { Shield, SlidersHorizontal } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { overview as settingsOverview } from '@/routes/settings';
import type { ContactFieldsPageProps } from '../contracts';
import { CustomFieldsPanel } from './custom-fields-panel';
import { TagManager } from './tag-manager';

export default function Fields({
  tags,
  customFields,
  canManage,
}: ContactFieldsPageProps) {
  function reload(only: string[]) {
    router.reload({ only });
  }

  return (
    <>
      <Head title="Campos personalizados" />

      <div className="max-w-3xl space-y-4">
        <Heading
          title="Campos y etiquetas"
          description="Campos extra y etiquetas de contacto para tu cuenta."
        />

        <TagManager
          tags={tags}
          canManage={canManage}
          onChanged={() => reload(['tags'])}
        />

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <SlidersHorizontal className="size-4 text-primary" />
              Campos personalizados
              <Badge variant="outline" className="ml-1 gap-1 font-medium">
                <Shield className="size-3" />
                Admin
              </Badge>
            </CardTitle>
            <CardDescription>
              Catálogo de campos personalizados para contactos, compartido en
              toda la cuenta.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <CustomFieldsPanel
              fields={customFields}
              canManage={canManage}
              onChanged={() => reload(['customFields'])}
            />
          </CardContent>
        </Card>
      </div>
    </>
  );
}

Fields.layout = {
  breadcrumbs: [
    { title: 'Settings', href: settingsOverview() },
    { title: 'Campos personalizados' },
  ],
};
