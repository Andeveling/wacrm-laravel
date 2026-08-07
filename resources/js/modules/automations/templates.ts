export interface AutomationTemplate {
  slug: string;
  name: string;
  description: string;
}

export const AUTOMATION_TEMPLATES: AutomationTemplate[] = [
  {
    slug: 'welcome_message',
    name: 'Mensaje de bienvenida',
    description: 'Responde automáticamente a contactos nuevos con un saludo.',
  },
  {
    slug: 'out_of_office',
    name: 'Fuera de oficina',
    description: 'Avisa cuando el equipo no está disponible.',
  },
  {
    slug: 'lead_qualifier',
    name: 'Calificador de prospectos',
    description: 'Hace preguntas para calificar un nuevo lead.',
  },
  {
    slug: 'follow_up_reminder',
    name: 'Recordatorio de seguimiento',
    description: 'Envía un recordatorio si no hay respuesta en 24h.',
  },
];
