package com.azure.demo.highcpu.controller;

import com.azure.demo.highcpu.events.CpuStressEvent;
import org.springframework.context.ApplicationEventPublisher;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

import java.util.LinkedHashMap;
import java.util.Map;

/**
 * REST controller that publishes CPU stress events.
 * The actual CPU work is handled by event listeners (delegates),
 * demonstrating the event/delegate pattern for high CPU scenarios.
 */
@RestController
public class CpuStressController {

    private final ApplicationEventPublisher eventPublisher;

    public CpuStressController(ApplicationEventPublisher eventPublisher) {
        this.eventPublisher = eventPublisher;
    }

    @GetMapping("/")
    public Map<String, Object> index() {
        Map<String, Object> info = new LinkedHashMap<>();
        info.put("application", "Azure App Service - High CPU Event/Delegate Demo (Java 21)");
        info.put("pattern", "Event/Delegate using Spring ApplicationEvent");
        info.put("endpoints", Map.of(
                "GET /stress", "Trigger CPU stress via event/delegate model",
                "GET /", "This info page"
        ));
        info.put("parameters", Map.of(
                "threads", "Number of CPU-burning threads (default: 4)",
                "duration", "Duration in seconds (default: 30)"
        ));
        info.put("example", "/stress?threads=4&duration=30");
        return info;
    }

    @GetMapping("/stress")
    public Map<String, Object> triggerStress(
            @RequestParam(defaultValue = "4") int threads,
            @RequestParam(defaultValue = "30") int duration) {

        // Publish the event — delegates (listeners) handle the CPU work
        eventPublisher.publishEvent(new CpuStressEvent(this, threads, duration));

        Map<String, Object> result = new LinkedHashMap<>();
        result.put("status", "completed");
        result.put("pattern", "event/delegate (Spring ApplicationEvent + @EventListener)");
        result.put("threadsUsed", threads);
        result.put("durationSeconds", duration);
        result.put("description",
                "Published CpuStressEvent which was handled by CpuStressListener delegate. "
                + "The listener spawned " + threads + " threads performing tight math loops "
                + "(sqrt, sin, cos) for " + duration + " seconds to drive CPU to 100%.");
        return result;
    }
}
