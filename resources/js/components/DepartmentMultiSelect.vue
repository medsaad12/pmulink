<script setup lang="ts">
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

type DepartmentOption = { id: number; name: string };

const props = withDefaults(
    defineProps<{
        departments: DepartmentOption[];
        modelValue: number[];
        disabled?: boolean;
        size?: 'default' | 'sm';
        id?: string;
    }>(),
    {
        disabled: false,
        size: 'default',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

const selectedIds = computed(() => new Set(props.modelValue));

const selectedDepartments = computed(() =>
    props.departments.filter((department) => selectedIds.value.has(department.id)),
);

const isDisabled = computed(() => props.disabled || props.departments.length === 0);

function toggleDepartment(departmentId: number): void {
    const next = new Set(props.modelValue);

    if (next.has(departmentId)) {
        next.delete(departmentId);
    } else {
        next.add(departmentId);
    }

    emit(
        'update:modelValue',
        [...next].sort((a, b) => a - b),
    );
}

function clearAll(): void {
    emit('update:modelValue', []);
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                :id="id"
                type="button"
                variant="outline"
                :disabled="isDisabled"
                :class="
                    cn(
                        'flex w-full items-center justify-between gap-2 font-normal',
                        size === 'sm' ? 'h-auto min-h-8 px-2 py-1' : 'h-auto min-h-9 px-3 py-1.5',
                    )
                "
            >
                <span
                    v-if="departments.length === 0"
                    class="text-muted-foreground"
                >
                    Aucun département
                </span>
                <span
                    v-else-if="selectedDepartments.length === 0"
                    :class="size === 'sm' ? 'text-xs text-muted-foreground' : 'text-sm text-muted-foreground'"
                >
                    Global · tous accès
                </span>
                <span v-else class="flex flex-1 flex-wrap gap-1">
                    <span
                        v-for="department in selectedDepartments"
                        :key="department.id"
                        class="inline-flex items-center gap-1 rounded bg-primary/10 px-1.5 py-0.5 text-xs font-medium text-primary"
                    >
                        {{ department.name }}
                    </span>
                </span>
                <ChevronsUpDown class="size-4 shrink-0 opacity-50" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            class="max-h-72 w-[var(--reka-dropdown-menu-trigger-width)] min-w-56 overflow-y-auto p-1"
            align="start"
        >
            <button
                type="button"
                class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                :class="selectedDepartments.length === 0 ? 'font-medium text-foreground' : ''"
                @click="clearAll"
            >
                <span
                    class="flex size-4 shrink-0 items-center justify-center rounded-full border"
                    :class="
                        selectedDepartments.length === 0
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-input'
                    "
                >
                    <Check
                        v-if="selectedDepartments.length === 0"
                        class="size-3"
                    />
                </span>
                Global (aucun département)
            </button>
            <div class="my-1 h-px bg-border" />
            <button
                v-for="department in departments"
                :key="department.id"
                type="button"
                class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm transition-colors hover:bg-accent hover:text-accent-foreground"
                @click="toggleDepartment(department.id)"
            >
                <span
                    class="flex size-4 shrink-0 items-center justify-center rounded border"
                    :class="
                        selectedIds.has(department.id)
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-input'
                    "
                >
                    <Check
                        v-if="selectedIds.has(department.id)"
                        class="size-3"
                    />
                </span>
                <span class="flex-1 truncate">{{ department.name }}</span>
            </button>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
