<script setup lang="ts">
import { LavertiaRenderer as Renderer } from '@lavertia/vue-ui';
import type { ComponentNode } from '@lavertia/ui';

defineProps<{ node: ComponentNode }>();
</script>
<template>
    <Renderer :node="node" />
</template>
