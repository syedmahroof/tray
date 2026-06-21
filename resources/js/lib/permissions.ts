export function resourceLabel(resource: string) {
    return resource
        .split('-')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

export function actionLabel(permission: string) {
    const action = permission.split('.').pop() ?? permission;

    return action.charAt(0).toUpperCase() + action.slice(1);
}
