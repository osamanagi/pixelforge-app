<script setup lang="ts">
import { Head, useForm, useHttp } from '@inertiajs/vue3';
import { ArrowUp, ImagePlus, Link2, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed } from 'vue';
import GenerationController from '@/actions/App/Http/Controllers/GenerationController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { SIZE_LABELS } from '@/lib/generation';
import { formatNanodollars } from '@/lib/money';
import { create } from '@/routes/generations';

const MAX_PROMPT_LENGTH = 4000;

const MAX_REFERENCE_IMAGES = 4;

const REFERENCE_IMAGE_PLACEHOLDER = 'https://example.com/reference.png';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Generate', href: create() }],
    },
});

const props = defineProps<{
    sizes: string[];
    qualities: string[];
}>();

const form = useForm({
    prompt: '',
    size: props.sizes[0],
    quality: 'medium',
    reference_images: [] as string[],
});

const estimate = useHttp<
    {
        prompt: string;
        size: string;
        quality: string;
        reference_image_count: number;
    },
    { estimated_price: number }
>({
    prompt: '',
    size: form.size,
    quality: form.quality,
    reference_image_count: 0,
});

const filledReferenceImageCount = computed(
    () => form.reference_images.filter((url) => url.trim() !== '').length,
);

const estimatedPrice = computed(() =>
    estimate.response
        ? formatNanodollars(estimate.response.estimated_price)
        : null,
);

const fieldErrors = computed(
    () => form.errors as Record<string, string | undefined>,
);

const canSubmit = computed(() => form.prompt.trim() !== '' && !form.processing);

watchDebounced(
    () => [
        form.prompt,
        form.size,
        form.quality,
        filledReferenceImageCount.value,
    ],
    () => {
        estimate.cancel();
        estimate.prompt = form.prompt;
        estimate.size = form.size;
        estimate.quality = form.quality;
        estimate.reference_image_count = filledReferenceImageCount.value;
        estimate.submit(GenerationController.estimate());
    },
    { debounce: 300, immediate: true },
);

function addReferenceImage(): void {
    form.reference_images.push('');
}

function removeReferenceImage(index: number): void {
    form.reference_images.splice(index, 1);
}

function submitOnEnter(event: KeyboardEvent): void {
    if (event.isComposing) {
        return;
    }

    event.preventDefault();
    submit();
}

function submit(): void {
    if (!canSubmit.value) {
        return;
    }

    form.submit(GenerationController.store(), {
        preserveScroll: true,
        onSuccess: () => form.reset('prompt', 'reference_images'),
    });
}
</script>

<template>
    <Head title="Generate" />

    <div
        class="flex flex-1 flex-col items-center justify-center px-4 py-10 sm:py-16"
    >
        <div class="w-full max-w-3xl">
            <h1
                class="mb-8 text-center text-2xl font-semibold tracking-tight sm:text-3xl"
            >
                What do you want to create?
            </h1>

            <form
                class="rounded-3xl border bg-card shadow-sm transition-[box-shadow,border-color] focus-within:border-ring/60 focus-within:shadow-md"
                @submit.prevent="submit"
            >
                <div
                    v-if="form.reference_images.length > 0"
                    class="flex flex-col gap-2 border-b px-4 pt-4 pb-3"
                >
                    <div
                        v-for="(url, index) in form.reference_images"
                        :key="index"
                        class="grid gap-1"
                    >
                        <div
                            class="flex items-center gap-2 rounded-xl bg-muted/60 py-1 pr-1 pl-3"
                        >
                            <Link2
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <input
                                v-model="form.reference_images[index]"
                                type="url"
                                :placeholder="REFERENCE_IMAGE_PLACEHOLDER"
                                :aria-label="`Reference image ${index + 1}`"
                                :aria-invalid="
                                    !!fieldErrors[`reference_images.${index}`]
                                "
                                class="min-w-0 flex-1 bg-transparent py-1 text-sm outline-none placeholder:text-muted-foreground"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-7 rounded-full"
                                :aria-label="`Remove reference image ${index + 1}`"
                                @click="removeReferenceImage(index)"
                            >
                                <X />
                            </Button>
                        </div>
                        <InputError
                            class="px-3"
                            :message="fieldErrors[`reference_images.${index}`]"
                        />
                    </div>
                </div>

                <textarea
                    v-model="form.prompt"
                    autofocus
                    rows="1"
                    :maxlength="MAX_PROMPT_LENGTH"
                    aria-label="Prompt"
                    placeholder="Describe the image you want to create…"
                    class="block [field-sizing:content] max-h-72 min-h-28 w-full resize-none bg-transparent px-5 pt-5 pb-2 text-base outline-none placeholder:text-muted-foreground"
                    :aria-invalid="!!form.errors.prompt"
                    @keydown.enter.exact="submitOnEnter"
                ></textarea>

                <div class="flex flex-wrap items-center gap-2 px-3 pt-1 pb-3">
                    <TooltipProvider :delay-duration="300">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="rounded-full"
                                    aria-label="Add reference image"
                                    :disabled="
                                        form.reference_images.length >=
                                        MAX_REFERENCE_IMAGES
                                    "
                                    @click="addReferenceImage"
                                >
                                    <ImagePlus />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                Add a reference image URL (up to
                                {{ MAX_REFERENCE_IMAGES }})
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    <Select v-model="form.size">
                        <SelectTrigger
                            size="sm"
                            class="rounded-full"
                            aria-label="Size"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="size in sizes"
                                :key="size"
                                :value="size"
                            >
                                {{ SIZE_LABELS[size] ?? size }}
                                <span class="text-muted-foreground">{{
                                    size
                                }}</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="form.quality">
                        <SelectTrigger
                            size="sm"
                            class="rounded-full capitalize"
                            aria-label="Quality"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="quality in qualities"
                                :key="quality"
                                :value="quality"
                                class="capitalize"
                            >
                                {{ quality }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <div class="ml-auto flex items-center gap-3">
                        <span
                            v-if="form.prompt.length > MAX_PROMPT_LENGTH - 500"
                            class="text-xs text-muted-foreground tabular-nums"
                        >
                            {{ form.prompt.length }} / {{ MAX_PROMPT_LENGTH }}
                        </span>

                        <span
                            class="flex items-center gap-1.5 text-sm text-muted-foreground tabular-nums"
                            aria-live="polite"
                        >
                            <Spinner
                                v-if="estimate.processing"
                                class="size-3.5"
                            />
                            <template v-if="estimatedPrice">
                                up to
                                <span class="font-medium text-foreground">{{
                                    estimatedPrice
                                }}</span>
                            </template>
                        </span>

                        <Button
                            type="submit"
                            size="icon"
                            class="rounded-full"
                            aria-label="Generate"
                            :disabled="!canSubmit"
                        >
                            <Spinner v-if="form.processing" />
                            <ArrowUp v-else />
                        </Button>
                    </div>
                </div>
            </form>

            <div class="mt-2 grid gap-1 px-4">
                <InputError :message="form.errors.prompt" />
                <InputError :message="form.errors.size" />
                <InputError :message="form.errors.quality" />
                <InputError :message="form.errors.reference_images" />
            </div>

            <p class="mt-3 text-center text-xs text-muted-foreground">
                The estimate is the most you'll pay. You're charged for what the
                generation actually uses, and failed generations are free.
            </p>
        </div>
    </div>
</template>
