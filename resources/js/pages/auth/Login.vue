<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { computed } from 'vue';

defineOptions({
    layout: {
        title: '',
        description: '',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage();

const redirectedEmailError = computed(() => {
    const raw = page.props.errors as
        | Record<string, string | string[] | undefined>
        | undefined;

    const value = raw?.email;

    if (Array.isArray(value)) {
        return value[0] ?? undefined;
    }

    return value;
});
</script>

<template>
    <Head title="Connexion" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Adresse e-mail</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email ?? redirectedEmailError" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Mot de passe</Label>
                    <!-- <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Mot de passe oublié ?
                    </TextLink> -->
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Mot de passe"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Se souvenir de moi</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full bg-[#7C3AED] text-white hover:bg-[#6D28D9]"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Connexion
            </Button>

            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t" />
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-background px-2 text-muted-foreground">Ou</span>
                </div>
            </div>

            <Button
                as="a"
                href="/auth/zoho/redirect"
                variant="outline"
                class="w-full"
                :tabindex="6"
                data-test="zoho-login-button"
            >
                Connexion avec Zoho
            </Button>
        </div>

    </Form>
</template>
