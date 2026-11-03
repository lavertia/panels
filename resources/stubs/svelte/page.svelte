<script lang="ts">
    import { LavertiaPage as Page } from '@lavertia/svelte-ui';
    import type { LavertiaPayload } from '@lavertia/ui';

    let { lavertia }: { lavertia: LavertiaPayload } = $props();
</script>

<Page {lavertia} />
