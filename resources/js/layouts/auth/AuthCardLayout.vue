<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10"
    >
        <div class="flex w-full max-w-md flex-col gap-6">
            <div class="flex flex-col gap-6">
                <Card class="rounded-xl">
                    <CardHeader class="items-center px-10 pt-8 pb-0 text-center">
                        <Link
                            :href="home()"
                            class="flex items-center justify-center font-medium"
                        >
                            <img
                                src="/logos/logopmo.png"
                                alt="PMOLINK"
                                class="h-20 w-auto object-contain object-center"
                            />
                        </Link>
                        <CardTitle v-if="title" class="text-xl">{{ title }}</CardTitle>
                        <CardDescription v-if="description">
                            {{ description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="px-10 py-8">
                        <slot />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
