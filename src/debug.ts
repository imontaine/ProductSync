import {heartbeatUrl} from "./config.ts";
import fetch from 'node-fetch';

type AnyFunction = (...args: any[]) => any;

export function decorate<T extends AnyFunction>(
    originalFn: T,
    beforeFn: (args: any) => any | null,
    afterFn?: (result: any) => any,
): T {
    return function (...args: Parameters<T>): ReturnType<T> {
        // @ts-ignore
        beforeFn && beforeFn(...args);
        const result = originalFn(...args);
        if (afterFn) {
            if (result instanceof Promise) {
                result.then(function () {
                    // @ts-ignore
                    afterFn(arguments);
                    return arguments;
                });
            } else {
                afterFn(result);
            }
        }
        return result;
    } as T;
}

export const heartbeat = () => fetch(heartbeatUrl)

export function withLog<T extends (...args: any[]) => any>(
    func: T,
): (...funcArgs: Parameters<T>) => ReturnType<T> {
    const funcName = func.name;

    // Return a new function that tracks how long the original took
    return (...args: Parameters<T>): ReturnType<T> => {
        const results = func(...args);
        console.log('called ', funcName);
        return results;
    };
}

export function withLogs<T extends (...args: any[]) => any>(
    func: T,
): (...funcArgs: Parameters<T>) => ReturnType<T> {
    const funcName = func.name;

    // Return a new function that tracks how long the original took
    return (...args: Parameters<T>): ReturnType<T> => {
        const results = func(...args);
        console.log('called ', funcName);
        return results;
    };
}

export const logger = (_target: any, _propertyKey: any, descriptor: PropertyDescriptor): void => {
    // descriptor.value is the original function
    const oldValue = descriptor.value; // save in aux

    // replace the value with another function
    // If you use `descriptor.value = () => {}` instead of function,
    //   you cannot access the `arguments`
    descriptor.value = function () {
        console.log('Start FN');
        const response = oldValue.apply(this, arguments);
        console.log('End FN');

        return response;
    };
};
