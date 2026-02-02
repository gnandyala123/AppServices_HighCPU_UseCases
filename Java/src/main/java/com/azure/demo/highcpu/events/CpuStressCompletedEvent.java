package com.azure.demo.highcpu.events;

import org.springframework.context.ApplicationEvent;

/**
 * Event published when CPU stress work completes.
 * Delegates (listeners) can react to completion.
 */
public class CpuStressCompletedEvent extends ApplicationEvent {

    private final int threadCount;
    private final long totalIterations;
    private final long elapsedMillis;

    public CpuStressCompletedEvent(Object source, int threadCount, long totalIterations, long elapsedMillis) {
        super(source);
        this.threadCount = threadCount;
        this.totalIterations = totalIterations;
        this.elapsedMillis = elapsedMillis;
    }

    public int getThreadCount() {
        return threadCount;
    }

    public long getTotalIterations() {
        return totalIterations;
    }

    public long getElapsedMillis() {
        return elapsedMillis;
    }
}
