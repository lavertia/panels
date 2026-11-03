// App-owned integration. The UI Kit is optional; do not move it into Lavertia Core.
import { LavertiaPage, createUiRegistry } from '@lavertia/react-ui';
import { createShadcnKit } from '@lavertia/ui-shadcn';
import type { LavertiaPayload } from '@lavertia/ui';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

const registry = createUiRegistry().registerKit(createShadcnKit({
    Input,
    Label,
    Textarea,
    Checkbox,
    Button,
    Card,
    CardContent,
}));

export default function LavertiaShadcnPage({ lavertia }: { lavertia: LavertiaPayload }) {
    return <LavertiaPage lavertia={lavertia} registry={registry} />;
}
