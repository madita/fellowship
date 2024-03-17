export function middlewarePipeline(context, middleware, index) {
    // Get the next middleware from the list using the provided index.
    const nextMiddleware = middleware[index];

    // If there's no next middleware, return the next method from the context.
    if (!nextMiddleware) {
        return context.next;
    }

    // This function handles the progress to the next middleware or final route navigation.
    return (params) => {
        // If params are provided, directly navigate to the intended route.
        if (params) {
            return context.next(params);
        }

        // Execute the current middleware and pass the modified context along.
        // The context's 'next' method is replaced with a new middlewarePipeline function, progressing to the next middleware.
        nextMiddleware({
            ...context,
            next: middlewarePipeline(context, middleware, index + 1)
        });
    }
}
