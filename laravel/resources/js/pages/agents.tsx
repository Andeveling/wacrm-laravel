import { Head } from '@inertiajs/react';
import { BarChart3, Bot, Settings2, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { AiPlayground } from '@/components/agents/ai-playground';
import { AiUsageCard } from '@/components/agents/ai-usage';
import { Badge } from '@/components/ui/badge';
import {
  Card,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

type Tab = 'playground' | 'setup' | 'usage';

export default function AgentsPage() {
  const [tab, setTab] = useState<Tab>('playground');

  return (
    <>
      <Head title="Agentes de IA" />

      <div>
        <div className="flex items-center gap-2">
          <Bot className="h-6 w-6 text-primary" />
          <h1 className="text-2xl font-bold tracking-tight text-foreground">
            Agentes de IA
          </h1>
        </div>
        <p className="mt-1 text-sm text-muted-foreground">
          Tu agente de IA con llave propia — configúralo y pruébalo en el
          playground antes de que responda a clientes en el inbox.
        </p>

        <Tabs
          value={tab}
          onValueChange={(v) => setTab(v as Tab)}
          className="mt-6"
        >
          <TabsList>
            <TabsTrigger value="playground">
              <Sparkles className="mr-1.5 h-4 w-4" /> Playground
            </TabsTrigger>
            <TabsTrigger value="setup">
              <Settings2 className="mr-1.5 h-4 w-4" /> Configuración
            </TabsTrigger>
            <TabsTrigger value="usage">
              <BarChart3 className="mr-1.5 h-4 w-4" /> Consumo
            </TabsTrigger>
          </TabsList>

          <TabsContent value="playground" className="mt-4">
            <AiPlayground onGoToSetup={() => setTab('setup')} />
          </TabsContent>

          <TabsContent value="setup" className="mt-4">
            <Card>
              <CardHeader>
                <div className="flex items-start justify-between gap-3">
                  <CardTitle>Configuración del agente</CardTitle>
                  <Badge variant="outline">Próximamente</Badge>
                </div>
                <CardDescription>
                  La configuración de proveedor, modelo y base de conocimiento
                  aún no está disponible.
                </CardDescription>
              </CardHeader>
            </Card>
          </TabsContent>

          <TabsContent value="usage" className="mt-4">
            <AiUsageCard />
          </TabsContent>
        </Tabs>
      </div>
    </>
  );
}

AgentsPage.layout = {
  breadcrumbs: [
    {
      title: 'Agentes de IA',
      href: '/agents',
    },
  ],
};
