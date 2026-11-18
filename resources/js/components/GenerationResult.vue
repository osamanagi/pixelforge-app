<script setup lang="ts">
import { CircleAlert, ImageOff } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Spinner } from '@/components/ui/spinner';
import { SIZE_ASPECT_CLASSES, STATUS_LABELS } from '@/lib/generation';
import { formatNanodollars } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { Generation } from '@/types';

const props = defineProps<{ generation: Generation }>();

const imageFailedToLoad = ref(false);

const showImage = computed(
    () => props.generation.image_url !== null && !imageFailedToLoad.value,
);

const chargedLabel = computed(() => {
    if (props.generation.status === 'failed') {
        return '$0 (failed generations are free)';
    }

    if (props.generation.actual_cost === null) {
        return 'Calculated when it finishes';
    }

    return formatNanodollars(props.generation.actual_cost);
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <div
            :class="
                cn(
                    'relative w-full max-w-xl overflow-hidden rounded-2xl border bg-muted/60',
                    SIZE_ASPECT_CLASSES[generation.size] ?? 'aspect-square',
                )
            "
        >
            <a
                v-if="showImage"
                :href="generation.image_url ?? undefined"
                target="_blank"
                rel="noopener"
            >
                <img
                    :src="generation.image_url ?? undefined"
                    :alt="generation.prompt"
                    class="size-full object-cover"
                    @error="imageFailedToLoad = true"
                />
            </a>

            <div
                v-else-if="!generation.is_finished"
                class="flex size-full animate-pulse flex-col items-center justify-center gap-2 text-sm text-muted-foreground"
            >
                <Spinner class="size-5" />
                {{ STATUS_LABELS[generation.status] }}…
            </div>

            <div
                v-else-if="generation.status === 'failed'"
                class="flex size-full flex-col items-center justify-center gap-2 p-6 text-center text-sm text-destructive"
            >
                <CircleAlert class="size-6" />
                {{ generation.error_message ?? 'The generation failed.' }}
            </div>

            <div
                v-else
                class="flex size-full flex-col items-center justify-center gap-2 p-6 text-center text-sm text-muted-foreground"
            >
                <ImageOff class="size-6" />
                The image link has expired.
            </div>
        </div>

        <dl
            class="grid max-w-xl grid-cols-2 gap-2 rounded-xl bg-muted/50 p-3 text-xs tabular-nums"
        >
            <div>
                <dt class="text-muted-foreground">Estimated</dt>
                <dd class="font-medium">
                    up to {{ formatNanodollars(generation.estimated_cost) }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Charged</dt>
                <dd
                    :class="
                        cn(
                            'font-medium',
                            generation.actual_cost === null &&
                                generation.status !== 'failed' &&
                                'text-muted-foreground',
                        )
                    "
                >
                    {{ chargedLabel }}
                </dd>
            </div>
        </dl>
    </div>
</template>
