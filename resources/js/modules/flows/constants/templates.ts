export interface FlowTemplate {
  slug: string;
  name: string;
  description: string;
  nodeCount: number;
}

export const FLOW_TEMPLATES: FlowTemplate[] = [
  {
    slug: 'faq_bot',
    name: 'Preguntas frecuentes',
    description: 'Responde preguntas comunes con un menú de opciones.',
    nodeCount: 5,
  },
  {
    slug: 'lead_capture',
    name: 'Captura de leads',
    description: 'Recolecta nombre y correo antes de derivar a un agente.',
    nodeCount: 4,
  },
  {
    slug: 'appointment',
    name: 'Agendar cita',
    description: 'Guía al contacto para agendar una cita.',
    nodeCount: 6,
  },
];
