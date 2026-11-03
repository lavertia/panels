<script setup lang="ts">
import { LavertiaPage as Page } from '@lavertia/vue-ui';
import type { LavertiaPayload } from '@lavertia/ui';

defineProps<{ lavertia: LavertiaPayload }>();
</script>
<template>
    <Page :lavertia="lavertia" />
</template>
