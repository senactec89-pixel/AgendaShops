import "reflect-metadata";
import { NestFactory } from "@nestjs/core";
import { AppModule } from "./app.module";

async function bootstrap() {
  const app = await NestFactory.create(AppModule);

  app.setGlobalPrefix("api/v1");
  app.enableCors({
    credentials: true,
    origin: process.env.WEB_URL ?? "http://localhost:3000",
  });

  const port = Number(process.env.API_PORT ?? 3333);
  await app.listen(port);
}

void bootstrap();

