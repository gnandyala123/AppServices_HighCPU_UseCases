package com.azure.demo.highcpu.events;

import org.springframework.context.ApplicationEvent;

/**
 * Event that signals CPU-intensive work should begin.
 * Acts as the "event" in the event/delegate model.
 */
public class CpuStressEvent extends ApplicationEvent {

    private final int threadCount;
    private final int durationSeconds;

    public CpuStressEvent(Object source, int threadCount, int durationSeconds) {
        super(source);
        this.threadCount = threadCount;
        this.durationSeconds = durationSeconds;
    }

    public int getThreadCount() {
        return threadCount;
    }

    public int getDurationSeconds() {
        return durationSeconds;
    }
}
