import type { LucideIcon } from 'lucide-react';
import {
    Apple,
    Briefcase,
    Car,
    CreditCard,
    Ellipsis,
    Film,
    GraduationCap,
    HeartPulse,
    House,
    Plane,
    Shield,
    ShoppingBag,
    Utensils,
    Wallet,
} from 'lucide-react';

const iconMap: Record<string, LucideIcon> = {
    'apple': Apple,
    'utensils': Utensils,
    'car': Car,
    'house': House,
    'film': Film,
    'shopping-bag': ShoppingBag,
    'heart-pulse': HeartPulse,
    'credit-card': CreditCard,
    'graduation-cap': GraduationCap,
    'plane': Plane,
    'shield': Shield,
    'wallet': Wallet,
    'briefcase': Briefcase,
    'ellipsis': Ellipsis,
};

export function CategoryIcon({ name, className }: { name: string | null; className?: string }) {
    if (!name) return null;
    const Icon = iconMap[name];
    if (!Icon) return null;
    return <Icon className={className} />;
}
