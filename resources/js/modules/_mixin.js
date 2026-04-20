/**
 * Copy all own properties from source to target. Regular properties are
 * assigned normally (compatible with Alpine's reactive proxy), while
 * getter/setter descriptors are defined via Object.defineProperty so
 * they remain live computed properties instead of being evaluated once.
 */
export function mixinModule(target, source) {
    for (const key of Object.keys(source)) {
        const desc = Object.getOwnPropertyDescriptor(source, key);
        if (desc.get || desc.set) {
            Object.defineProperty(target, key, desc);
        } else {
            target[key] = desc.value;
        }
    }
}
