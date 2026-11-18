<script setup lang="ts">
import { Link, usePage, usePoll } from '@inertiajs/vue3';
import { CircleAlert } from '@lucide/vue';
import { computed, watch } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Spinner } from '@/components/ui/spinner';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { show } from '@/routes/generations';

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const generations = computed(() => page.props.recentGenerations ?? []);

const hasUnfinishedGenerations = computed(() =>
    generations.value.some(
        (generation) => !['succeeded', 'failed'].includes(generation.status),
    ),
);

const { start, stop } = usePoll(
    2000,
    { only: ['recentGenerations', 'generation'] },
    { autoStart: false },
);

watch(
    hasUnfinishedGenerations,
    (hasUnfinished) => (hasUnfinished ? start() : stop()),
    { immediate: true },
);
</script>

<template>
    <SidebarGroup class="px-2 py-0 group-data-[collapsible=icon]:hidden">
        <SidebarGroupLabel>Recents</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem
                v-for="generation in generations"
                :key="generation.id"
            >
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(show(generation.id))"
                    :tooltip="generation.prompt"
                >
                    <Link :href="show(generation.id)">
                        <span class="truncate">{{ generation.prompt }}</span>
                        <Spinner
                            v-if="
                                !['succeeded', 'failed'].includes(
                                    generation.status,
                                )
                            "
                            class="ml-auto size-3.5 shrink-0 text-muted-foreground"
                            aria-label="In progress"
                        />
                        <CircleAlert
                            v-else-if="generation.status === 'failed'"
                            class="ml-auto size-3.5 shrink-0 text-destructive"
                            aria-label="Failed"
                        />
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>

            <p
                v-if="generations.length === 0"
                class="px-2 py-1.5 text-xs text-muted-foreground"
            >
                Your generations will show up here.
            </p>
        </SidebarMenu>
    </SidebarGroup>
</template>
