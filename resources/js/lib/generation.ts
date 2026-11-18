import type { GenerationStatus } from '@/types';

export const SIZE_LABELS: Record<string, string> = {
    '1024x1024': 'Square',
    '1536x1024': 'Landscape',
    '1024x1536': 'Portrait',
};

export const SIZE_ASPECT_CLASSES: Record<string, string> = {
    '1024x1024': 'aspect-square',
    '1536x1024': 'aspect-[3/2]',
    '1024x1536': 'aspect-[2/3]',
};

export const STATUS_LABELS: Record<GenerationStatus, string> = {
    pending: 'Submitting',
    queued: 'Queued',
    processing: 'Generating',
    succeeded: 'Done',
    failed: 'Failed',
};
