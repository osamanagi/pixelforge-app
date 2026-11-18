<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Link2, Plus, Sparkles } from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import GenerationResult from '@/components/GenerationResult.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { SIZE_LABELS, STATUS_LABELS } from '@/lib/generation';
import { cn } from '@/lib/utils';
import { create, show } from '@/routes/generations';
import type { Generation } from '@/types';

const props = defineProps<{ generation: Generation }>();

const shortPrompt = computed(() =>
    props.generation.prompt.length > 40
        ? `${props.generation.prompt.slice(0, 40)}…`
        : props.generation.prompt,
);

watchEffect(() =>
    setLayoutProps({
        breadcrumbs: [
            { title: shortPrompt.value, href: show(props.generation.id) },
        ],
    }),
);

const statusClasses = computed(
    () =>
        ({
            pending: 'bg-muted text-muted-foreground',
            queued: 'bg-muted text-muted-foreground',
            processing:
                'bg-blue-500/10 text-blue-700 dark:bg-blue-400/15 dark:text-blue-300',
            succeeded:
                'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300',
            failed: 'bg-destructive/10 text-destructive',
        })[props.generation.status],
);

const createdAt = computed(() =>
    new Date(props.generation.created_at).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }),
);
</script>

<template>
    <Head :title="shortPrompt" />

    <div
        class="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-8 sm:py-12"
    >
        <div class="flex justify-end">
            <div
                class="flex max-w-[85%] flex-col gap-2 rounded-3xl bg-muted px-5 py-3"
            >
                <p class="text-base whitespace-pre-wrap">
                    {{ generation.prompt }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ SIZE_LABELS[generation.size] ?? generation.size }} ·
                    <span class="capitalize">{{ generation.quality }}</span>
                    · {{ createdAt }}
                </p>
                <ul
                    v-if="generation.reference_images.length > 0"
                    class="flex flex-col gap-1 text-xs"
                >
                    <li
                        v-for="url in generation.reference_images"
                        :key="url"
                        class="flex min-w-0 items-center gap-1.5 text-muted-foreground"
                    >
                        <Link2 class="size-3.5 shrink-0" />
                        <a
                            :href="url"
                            target="_blank"
                            rel="noopener"
                            class="truncate underline-offset-2 hover:underline"
                            >{{ url }}</a
                        >
                    </li>
                </ul>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <div class="flex items-center gap-2 text-sm">
                <Sparkles class="size-4 text-muted-foreground" />
                <span class="font-medium">Pixelforge</span>
                <Badge :class="cn('border-transparent', statusClasses)">
                    <Spinner v-if="!generation.is_finished" class="size-3" />
                    {{ STATUS_LABELS[generation.status] }}
                </Badge>
            </div>

            <GenerationResult :generation="generation" />
        </div>

        <div class="flex justify-center">
            <Button as-child variant="outline" class="rounded-full">
                <Link :href="create()">
                    <Plus />
                    New generation
                </Link>
            </Button>
        </div>
    </div>
</template>
